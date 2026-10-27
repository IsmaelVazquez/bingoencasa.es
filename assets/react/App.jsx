import React, { useEffect, useRef, useState } from 'react';
import confetti from 'canvas-confetti';
import QRCodeStyling from 'qr-code-styling';
import logoB from '@assets/logo-b.png';

const playSoundEffect = (type) => {
    const AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    try {
        const ctx = new AudioContext();
        if (type === 'WIN') {
            [0, 0.2, 0.4, 0.6].forEach((t, i) => {
                const osc = ctx.createOscillator(); const gain = ctx.createGain();
                osc.type = 'triangle'; osc.frequency.value = 523.25 * (1 + i * 0.25);
                gain.gain.setValueAtTime(0.1, ctx.currentTime + t); gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + t + 0.5);
                osc.connect(gain); gain.connect(ctx.destination); osc.start(ctx.currentTime + t); osc.stop(ctx.currentTime + t + 0.5);
            });
        } else if (type === 'FAIL') {
            [0, 0.3, 0.6].forEach((t, i) => {
                const osc = ctx.createOscillator(); const gain = ctx.createGain();
                osc.type = 'sawtooth'; osc.frequency.value = 200 - (i * 50);
                gain.gain.setValueAtTime(0.1, ctx.currentTime + t); gain.gain.linearRampToValueAtTime(0.001, ctx.currentTime + t + 0.4);
                osc.connect(gain); gain.connect(ctx.destination); osc.start(ctx.currentTime + t); osc.stop(ctx.currentTime + t + 0.4);
            });
        }
    } catch (e) { console.error("Audio error", e); }
};

// --- BINGO CARD GENERATOR (Spanish Standard: 15 numbers, max 2 per column, 5 per row) ---
const generateBingoCard = (seed) => {
    let state = seed;
    const rand = () => { state = (state * 9301 + 49297) % 233280; return state / 233280; };
    const shuffle = (arr) => {
        const result = [...arr];
        for (let i = result.length - 1; i > 0; i--) {
            const j = Math.floor(rand() * (i + 1));
            [result[i], result[j]] = [result[j], result[i]];
        }
        return result;
    };

    const colRanges = [[1, 9], [10, 19], [20, 29], [30, 39], [40, 49], [50, 59], [60, 69], [70, 79], [80, 90]];

    // Determine column counts: 6 columns with 2 numbers, 3 columns with 1 number = 15 total
    const colCounts = [2, 2, 2, 2, 2, 2, 1, 1, 1];
    const shuffledCounts = shuffle(colCounts);

    // Generate numbers for each column
    const cols = [];
    for (let c = 0; c < 9; c++) {
        const [min, max] = colRanges[c];
        const count = shuffledCounts[c];
        const colNums = [];
        while (colNums.length < count) {
            const num = Math.floor(rand() * (max - min + 1)) + min;
            if (!colNums.includes(num)) colNums.push(num);
        }
        colNums.sort((a, b) => a - b);
        cols.push(colNums);
    }

    // Initialize grid (3 rows x 9 columns)
    const grid = Array.from({ length: 3 }, () => Array(9).fill(null));
    const rowCounts = [0, 0, 0];

    // Place numbers with constraint: each row must have exactly 5 numbers
    // First, place columns with 2 numbers
    const twoNumCols = [];
    const oneNumCols = [];
    for (let c = 0; c < 9; c++) {
        if (cols[c].length === 2) twoNumCols.push(c);
        else oneNumCols.push(c);
    }

    // For 2-number columns, choose which 2 of 3 rows to use
    const rowPairs = [[0, 1], [0, 2], [1, 2]];
    for (const c of shuffle(twoNumCols)) {
        // Find valid row pairs (rows that haven't reached 5 yet)
        const validPairs = rowPairs.filter(([r1, r2]) => rowCounts[r1] < 5 && rowCounts[r2] < 5);
        if (validPairs.length === 0) continue;
        const pair = validPairs[Math.floor(rand() * validPairs.length)];
        grid[pair[0]][c] = cols[c][0];
        grid[pair[1]][c] = cols[c][1];
        rowCounts[pair[0]]++;
        rowCounts[pair[1]]++;
    }

    // For 1-number columns, choose which row to use
    for (const c of shuffle(oneNumCols)) {
        // Find rows that haven't reached 5 yet
        const validRows = [0, 1, 2].filter(r => rowCounts[r] < 5);
        if (validRows.length === 0) continue;
        const row = validRows[Math.floor(rand() * validRows.length)];
        grid[row][c] = cols[c][0];
        rowCounts[row]++;
    }

    return { id: seed, grid: grid, flat: cols.flat().sort((a, b) => a - b) };
};

const NICKNAMES = { 1: "El Galán", 15: "La Niña Bonita", 22: "Los Patitos", 33: "La Edad de Cristo", 69: "Ida y Vuelta", 11: "Los Clavos", 90: "El Abuelo", 10: "La Rosa", 5: "El Gato", 7: "La Pipa", 88: "Las Calabazas", 55: "Los Municipales", 25: "Navidad" };

const Icons = {
    Play: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>,
    Pause: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect x="6" y="4" width="4" height="16"></rect><rect x="14" y="4" width="4" height="16"></rect></svg>,
    RotateCcw: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path><path d="M3 3v5h5"></path></svg>,
    Volume2: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14"></path><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>,
    VolumeX: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line></svg>,
    Trophy: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="M8 21h8"></path><path d="M12 17v4"></path><path d="M7 4h10"></path><path d="M17 4v6a5 5 0 0 1-10 0V4"></path><path d="M7 7H5a2 2 0 0 0-2 2v2a2 2 0 0 0 2 2h2"></path><path d="M17 7h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-2"></path></svg>,
    List: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>,
    Check: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><polyline points="20 6 9 17 4 12"></polyline></svg>,
    X: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>,
    Search: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>,
    Mic: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path><path d="M19 10v2a7 7 0 0 1-14 0v-2"></path><line x1="12" y1="19" x2="12" y2="23"></line><line x1="8" y1="23" x2="16" y2="23"></line></svg>,
    Users: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>,
    Tv: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect x="2" y="7" width="20" height="15" rx="2" ry="2"></rect><polyline points="17 2 12 7 7 2"></polyline></svg>,
    Printer: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>,
    Copy: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>,
    Link: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>,
    Gauge: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><path d="m12 14 4-4"></path><path d="M3.34 19a10 10 0 1 1 17.32 0"></path></svg>
};

const GlassButton = ({ children, onClick, active = false, variant = 'primary', disabled = false, className = '', ...rest }) => {
    const baseStyles = "px-4 py-3 rounded-2xl font-bold transition-all duration-300 transform active:scale-95 flex items-center justify-center gap-2 backdrop-blur-md border shadow-lg select-none";
    const variants = {
        primary: active ? "bg-white text-black border-white shadow-[0_0_20px_rgba(255,255,255,0.3)]" : "bg-white/10 text-white border-white/10 hover:bg-white/20",
        danger: "bg-red-500/20 text-red-200 border-red-500/30 hover:bg-red-500/30 hover:border-red-400",
        ghost: "bg-transparent border-transparent text-white/60 hover:text-white hover:bg-white/5",
        line: "bg-cyan-500/20 text-cyan-200 border-cyan-500/30 hover:bg-cyan-500/30 hover:border-cyan-400 hover:shadow-[0_0_15px_rgba(6,182,212,0.3)]",
        bingo: "bg-yellow-500/20 text-yellow-200 border-yellow-500/30 hover:bg-yellow-500/30 hover:border-yellow-400 hover:shadow-[0_0_15px_rgba(234,179,8,0.3)]",
        success: "bg-green-500/20 text-green-200 border-green-500/30 hover:bg-green-500/30",
    };
    return <button {...rest} onClick={onClick} disabled={disabled} className={`${baseStyles} ${variants[variant]} ${disabled ? 'opacity-30 cursor-not-allowed transform-none' : ''} ${className}`}>{children}</button>;
};

const Ball3D = ({ number, size = "md", animate = false }) => {
    const sizeClasses = {
        sm: "w-7 h-7 sm:w-8 sm:h-8 md:w-10 md:h-10 lg:w-12 lg:h-12 text-[10px] sm:text-xs md:text-sm lg:text-lg",
        md: "w-14 h-14 sm:w-16 sm:h-16 md:w-20 md:h-20 text-lg sm:text-xl md:text-2xl",
        xl: "w-20 h-20 sm:w-24 sm:h-24 md:w-32 md:h-32 lg:w-40 lg:h-40 xl:w-48 xl:h-48 text-3xl sm:text-4xl md:text-5xl lg:text-6xl xl:text-7xl"
    };
    return <div className={`sphere rounded-full aspect-square flex items-center justify-center text-slate-800 font-bold relative z-10 ${sizeClasses[size]} ${animate ? 'animate-enter' : ''}`}><span className="relative z-20 tracking-tighter" style={{ fontFamily: 'Inter, sans-serif' }}>{number}</span></div>;
};

const BetaBadge = () => (
    <div className="fixed top-4 right-4 z-[9999]">
        <div className="bg-gradient-to-r from-[#8b5cf6] to-[#f25dad] text-white text-xs font-bold px-3 py-1 rounded-full shadow-lg animate-pulse">
            BETA
        </div>
    </div>
);

const StatusBadge = ({ status }) => {
    const styles = { PLAYING: "bg-green-500/20 text-green-300 border-green-500/30", PAUSED: "bg-yellow-500/20 text-yellow-300 border-yellow-500/30", CHECKING: "bg-blue-500/20 text-blue-300 border-blue-500/30", STOPPED: "bg-slate-500/20 text-slate-300 border-slate-500/30" };
    const labels = { PLAYING: "EN JUEGO", PAUSED: "PAUSA", CHECKING: "COMPROBANDO", STOPPED: "ESPERANDO" };
    return <div className={`px-2 py-1 md:px-3 md:py-1 rounded-full text-[9px] md:text-[10px] font-bold tracking-widest border backdrop-blur-md ${styles[status]}`}><span className="w-1.5 h-1.5 rounded-full bg-current inline-block mr-1 md:mr-2 animate-pulse"></span>{labels[status]}</div>;
};

const ThraxLogo = () => (
    <img src="/assets/Thrax.es.svg" alt="Thrax.es" className="h-4 md:h-5 w-auto" />
);

// --- BINGO LOGO COMPONENT (CLEAN VERSION) ---
const BingoLogo = ({ className }) => (
    <div className={`${className} relative flex items-center justify-center transition-transform hover:scale-105 duration-300`}>
        <img
            src="/assets/logo-b.png"
            alt="BingoEnCasa"
            className="w-full h-full object-contain drop-shadow-2xl"
            onError={(e) => {
                e.target.style.display = 'none';
                e.target.parentNode.innerHTML = '<span class="text-white font-black text-4xl drop-shadow-[0_0_15px_rgba(255,255,255,0.5)]">B</span>';
            }}
        />
    </div>
);

// --- PRINT FUNCTION - 5 CARDS PER PAGE HORIZONTAL (USES API) - INK SAVING ---
const printCards = async (numCards = 12) => {
    try {
        const response = await fetch(`/api/cartones/plantillas?cantidad=${numCards}`);
        if (!response.ok) {
            const error = await response.json();
            alert(error.mensaje || 'Error al obtener cartones');
            return;
        }
        const data = await response.json();
        const cartones = data.cartones;

        if (!cartones || cartones.length === 0) {
            alert('No se encontraron cartones en ese rango');
            return;
        }

        const printWindow = window.open('', '_blank');
        if (!printWindow) { alert("Por favor, permite los pop-ups para imprimir"); return; }

        let htmlContent = `
            <html>
            <head>
                <title>Cartones BingoEnCasa.es</title>
                <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
                <style>
                    @media print {
                        @page { margin: 4mm; size: A4 portrait; }
                        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                        .no-print { display: none !important; }
                        .page-break { page-break-after: always; }
                    }
                    * { box-sizing: border-box; margin: 0; padding: 0; }
                    body { font-family: 'Inter', sans-serif; background: #f0f0f0; }

                    .print-page {
                        width: 202mm;
                        min-height: 289mm;
                        margin: 0 auto;
                        background: white;
                        padding: 4mm;
                        display: flex;
                        flex-direction: column;
                        gap: 3mm;
                    }

                    .card-wrapper {
                        border: 2px solid #1a1a2e;
                        border-radius: 3px;
                        overflow: hidden;
                        background: white;
                        display: flex;
                        flex-direction: row;
                        height: 54mm;
                    }

                    .card-sidebar {
                        width: 16mm;
                        background: #1a1a2e;
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        justify-content: space-between;
                        padding: 2mm 1mm;
                        color: white;
                    }

                    .sidebar-top {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        gap: 1mm;
                    }

                    .card-logo {
                        width: 8mm;
                        height: auto;
                    }

                    .card-number {
                        font-size: 7pt;
                        font-weight: 900;
                        letter-spacing: 0.5px;
                        background: white;
                        color: #1a1a2e;
                        padding: 1mm 2mm;
                        border-radius: 2px;
                    }

                    .card-brand {
                        font-size: 4pt;
                        font-weight: 700;
                        margin-top: 4mm;
                        letter-spacing: 0.3px;
                        opacity: 0.8;
                    }

                    .sidebar-bottom {
                        display: flex;
                        flex-direction: column;
                        align-items: center;
                        gap: 5mm;
                    }

                    .thrax-logo {
                        width: 16mm;
                        height: auto;
                        filter: brightness(0) invert(1);
                        opacity: 0.9;
                        transform: rotate(-90deg);
                    }

                    .grid-container {
                        flex: 1;
                        display: grid;
                        grid-template-columns: repeat(9, 1fr);
                        grid-template-rows: repeat(3, 1fr);
                    }

                    .cell {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 16pt;
                        font-weight: 800;
                        color: #1a1a2e;
                        border: 1px solid #ccc;
                        background: white;
                    }

                    .cell.empty {
                        background: #f5f5f5;
                    }

                    .print-btn {
                        position: fixed;
                        top: 15px;
                        right: 15px;
                        z-index: 100;
                        padding: 12px 24px;
                        font-size: 14px;
                        font-weight: bold;
                        background: #1a1a2e;
                        color: white;
                        border: none;
                        border-radius: 8px;
                        cursor: pointer;
                    }
                    .print-btn:hover { background: #2d2d44; }
                </style>
            </head>
            <body>
                <button onclick="window.print()" class="print-btn no-print">
                    Imprimir ${cartones.length} Cartones
                </button>
                <div class="print-page">
        `;

        cartones.forEach((carton, index) => {
            htmlContent += `
                <div class="card-wrapper">
                    <div class="card-sidebar">
                        <div class="sidebar-top">
                            <img src="/assets/logo-b.png" class="card-logo" alt="Logo" />
                            <span class="card-number">${carton.numero}</span>
                        </div>
                        <div class="sidebar-bottom">
                            <img src="/assets/Thrax.es.svg" class="thrax-logo" alt="Thrax.es" />
                            <span class="card-brand">BINGOENCASA.ES</span>
                        </div>
                    </div>
                    <div class="grid-container">
            `;

            carton.grid.forEach(row => {
                row.forEach(cell => {
                    htmlContent += `<div class="cell ${cell === null ? 'empty' : ''}">${cell || ''}</div>`;
                });
            });

            htmlContent += `
                    </div>
                </div>
            `;

            if ((index + 1) % 5 === 0 && index !== cartones.length - 1) {
                htmlContent += `</div><div class="page-break"></div><div class="print-page">`;
            }
        });

        htmlContent += `</div></body></html>`;
        printWindow.document.write(htmlContent);
        printWindow.document.close();
    } catch (error) {
        console.error('Error fetching cards:', error);
        alert('Error de conexión al obtener cartones');
    }
};

// --- LANDING PAGE COMPONENT ---
const LandingPage = ({ onSelectMode }) => {
    const [cardCount, setCardCount] = useState(12);
    const [isGenerating, setIsGenerating] = useState(false);
    const [systemStatus, setSystemStatus] = useState({ modoBombo: true, modoFiesta: true, fiestaLleno: false, salasDisponibles: 0, loading: true });

    useEffect(() => {
        fetch('/api/estado')
            .then(res => res.json())
            .then(data => setSystemStatus({ ...data, loading: false }))
            .catch(() => setSystemStatus(s => ({ ...s, loading: false })));
    }, []);

    const handleGeneratePdf = async () => {
        setIsGenerating(true);
        try {
            await printCards(cardCount);
        } finally {
            setIsGenerating(false);
        }
    };

    const handleSelectBombo = () => {
        if (!systemStatus.modoBombo) return;
        onSelectMode('SOLO');
    };

    const handleSelectFiesta = () => {
        if (!systemStatus.modoFiesta || systemStatus.fiestaLleno) return;
        onSelectMode('HOST');
    };

    return (
        <div className="h-screen w-screen flex flex-col items-center justify-center p-8 animate-enter overflow-hidden">
            <div className="text-center mb-8 md:mb-12 flex flex-col items-center">
                <div className="mb-4">
                    <BingoLogo className="w-24 h-24 md:w-32 md:h-32" />
                </div>
                <h1 className="text-3xl md:text-5xl font-black tracking-tight mb-2">BINGOENCASA<span className="opacity-50 font-normal">.ES</span></h1>
                <div className="flex items-center justify-center gap-2 opacity-80">
                    <span className="text-[10px] uppercase tracking-widest text-white/55">Powered by</span>
                    <img src="/assets/Thrax.es.svg" alt="Thrax.es" className="h-6 md:h-8 w-auto" />
                </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6 w-full max-w-4xl mb-8">
                {/* Option 1: Solo/Bombo */}
                <div
                    onClick={handleSelectBombo}
                    className={`glass-panel p-6 md:p-8 rounded-3xl transition-all group border ${
                        systemStatus.modoBombo
                            ? 'cursor-pointer hover:bg-white/5 border-white/10 hover:border-white/20 hover:scale-[1.02]'
                            : 'cursor-not-allowed opacity-50 border-white/5'
                    }`}
                >
                    <div className="w-12 h-12 md:w-16 md:h-16 rounded-full bg-blue-500/20 text-blue-300 flex items-center justify-center mb-4 md:mb-6 group-hover:scale-110 transition-transform">
                        <Icons.Tv className="w-6 h-6 md:w-8 md:h-8" />
                    </div>
                    <h2 className="text-xl md:text-2xl font-bold mb-2">Modo Bombo (Clásico)</h2>
                    <p className="text-sm md:text-base text-white/50 mb-4 md:mb-6">Usa la TV solo para cantar números. Ideal si ya tenéis cartones impresos o físicos.</p>
                    {systemStatus.modoBombo ? (
                        <div className="flex items-center text-blue-300 text-xs md:text-sm font-bold uppercase tracking-widest">
                            Jugar Ahora <span className="ml-2 group-hover:translate-x-1 transition-transform">→</span>
                        </div>
                    ) : (
                        <div className="text-red-400 text-xs md:text-sm font-bold uppercase tracking-widest">
                            No Disponible
                        </div>
                    )}
                </div>

                {/* Option 2: Online Host */}
                <div
                    onClick={handleSelectFiesta}
                    className={`glass-panel p-6 md:p-8 rounded-3xl transition-all group border ${
                        systemStatus.modoFiesta && !systemStatus.fiestaLleno
                            ? 'cursor-pointer hover:bg-white/5 border-white/10 hover:border-white/20 hover:scale-[1.02]'
                            : 'cursor-not-allowed opacity-50 border-white/5'
                    }`}
                >
                    <div className="w-12 h-12 md:w-16 md:h-16 rounded-full bg-pink-500/20 text-pink-300 flex items-center justify-center mb-4 md:mb-6 group-hover:scale-110 transition-transform">
                        <Icons.Users className="w-6 h-6 md:w-8 md:h-8" />
                    </div>
                    <h2 className="text-xl md:text-2xl font-bold mb-2">Modo Fiesta (Online)</h2>
                    <p className="text-sm md:text-base text-white/50 mb-4 md:mb-6">Crea una sala interactiva. Tus amigos se unen y juegan desde sus móviles.</p>
                    {!systemStatus.modoFiesta ? (
                        <div className="text-red-400 text-xs md:text-sm font-bold uppercase tracking-widest">
                            No Disponible
                        </div>
                    ) : systemStatus.fiestaLleno ? (
                        <div className="text-yellow-400 text-xs md:text-sm font-bold uppercase tracking-widest">
                            Lleno - Espera unos minutos
                        </div>
                    ) : (
                        <div className="flex items-center text-pink-300 text-xs md:text-sm font-bold uppercase tracking-widest">
                            Crear Sala <span className="ml-2 group-hover:translate-x-1 transition-transform">→</span>
                        </div>
                    )}
                </div>
            </div>

            <div className="glass rounded-xl p-4 flex flex-col sm:flex-row items-center gap-3">
                <div className="flex items-center gap-2">
                    <Icons.Printer className="w-5 h-5 text-white/50" />
                    <span className="text-sm text-white/70">Imprimir</span>
                </div>
                <div className="flex items-center gap-2">
                    <input
                        type="number"
                        min="1"
                        max="200"
                        value={cardCount}
                        onChange={(e) => setCardCount(Math.min(200, Math.max(1, parseInt(e.target.value) || 1)))}
                        className="w-20 bg-white/10 border border-white/20 rounded-lg px-3 py-2 text-center text-white font-bold focus:outline-none focus:border-pink-500"
                    />
                    <span className="text-sm text-white/50">cartones</span>
                </div>
                <button
                    onClick={handleGeneratePdf}
                    disabled={isGenerating}
                    className="bg-gradient-to-r from-[#8b5cf6] via-[#ec4899] to-[#22d3ee] hover:opacity-90 px-5 py-2 rounded-lg font-bold text-sm uppercase tracking-wide transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                    {isGenerating ? (
                        <>
                            <span className="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                            Cargando...
                        </>
                    ) : (
                        'Generar PDF'
                    )}
                </button>
            </div>
        </div>
    );
};

// --- TAB ICONS FOR MOBILE ---
const TabIcons = {
    Controls: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>,
    Board: ({ className }) => <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className={className}><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line></svg>,
};

// Shuffle array using Fisher-Yates algorithm
const shuffleArray = (array) => {
    const shuffled = [...array];
    for (let i = shuffled.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
    }
    return shuffled;
};

// Generate initial shuffled pool of 1-90
const generateShuffledPool = () => shuffleArray(Array.from({ length: 90 }, (_, i) => i + 1));

// Sala del anfitrión de Modo Fiesta, para recuperarla si recarga la página.
const FIESTA_HOST_KEY = 'bingoEncasa_fiestaHost';
const loadFiestaHost = () => {
    try {
        const saved = JSON.parse(localStorage.getItem(FIESTA_HOST_KEY));
        return saved?.roomCode ? saved : null;
    } catch (e) {
        return null;
    }
};
const saveFiestaHost = (value) => {
    try {
        if (value) localStorage.setItem(FIESTA_HOST_KEY, JSON.stringify(value));
        else localStorage.removeItem(FIESTA_HOST_KEY);
    } catch (e) { /* almacenamiento no disponible */ }
};

// Mantiene la pantalla encendida mientras `active`; el navegador suelta el bloqueo
// al ocultar la pestaña, así que se vuelve a pedir al regresar.
const useWakeLock = (active) => {
    useEffect(() => {
        if (!active || !('wakeLock' in navigator)) return;
        let lock = null;
        let cancelled = false;

        const acquire = async () => {
            if (lock || document.visibilityState !== 'visible') return;
            try {
                const next = await navigator.wakeLock.request('screen');
                if (cancelled) { next.release(); return; }
                lock = next;
                lock.addEventListener('release', () => { lock = null; });
            } catch (e) { /* no soportado o sin permiso */ }
        };

        acquire();
        document.addEventListener('visibilitychange', acquire);
        return () => {
            cancelled = true;
            document.removeEventListener('visibilitychange', acquire);
            lock?.release().catch(() => {});
        };
    }, [active]);
};

// --- MAIN APP ---
const App = () => {
    // Sala de Modo Fiesta que quedó abierta antes de recargar (se valida contra el servidor al montar).
    const [savedFiesta] = useState(loadFiestaHost);
    const restoringGame = !!savedFiesta?.partidaId;

    const [appMode, setAppMode] = useState(savedFiesta ? (restoringGame ? 'SOLO' : 'HOST_LOBBY') : 'LANDING'); // LANDING, SOLO, HOST_LOBBY, HOST_GAME
    const [roomCode, setRoomCode] = useState(restoringGame ? '' : savedFiesta?.roomCode || '');
    const [roomName, setRoomName] = useState(restoringGame ? '' : savedFiesta?.roomName || '');
    const [roomPin, setRoomPin] = useState(restoringGame ? '' : savedFiesta?.roomPin || '');
    const [players, setPlayers] = useState([]);
    const [qrReady, setQrReady] = useState(false);
    const [urlCopied, setUrlCopied] = useState(false);
    const [isCreatingRoom, setIsCreatingRoom] = useState(false);
    const [partidaId, setPartidaId] = useState(savedFiesta?.partidaId || null);
    const [isStartingGame, setIsStartingGame] = useState(false);
    const [fiestaPlayers, setFiestaPlayers] = useState([]);
    const [isFiestaMode, setIsFiestaMode] = useState(restoringGame);
    const qrRef = useRef(null);
    const qrCodeInstance = useRef(null);

    // Mobile Tab State
    const [mobileTab, setMobileTab] = useState('controls'); // 'controls' | 'board'

    // LocalStorage keys
    const STORAGE_KEY = 'bingoEncasa_gameState';

    // Load initial state from localStorage
    const loadSavedState = () => {
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved) {
                return JSON.parse(saved);
            }
        } catch (e) {
            console.warn('Error loading saved game:', e);
        }
        return null;
    };

    // Al recuperar una partida de Modo Fiesta, las bolas llegan del servidor, no de la partida local.
    const savedState = restoringGame ? null : loadSavedState();

    // Game State - Two arrays: available (to draw from) and drawn (already called)
    const [numbers] = useState(Array.from({ length: 90 }, (_, i) => i + 1));
    const [availableNumbers, setAvailableNumbers] = useState(() => {
        if (savedState?.availableNumbers) {
            return savedState.availableNumbers;
        }
        // For old saves without availableNumbers, derive from drawnNumbers
        const drawn = savedState?.drawnNumbers || [];
        if (drawn.length > 0) {
            const remaining = Array.from({ length: 90 }, (_, i) => i + 1).filter(n => !drawn.includes(n));
            return shuffleArray(remaining);
        }
        return generateShuffledPool();
    });
    const [drawnNumbers, setDrawnNumbers] = useState(savedState?.drawnNumbers || []);
    const [currentNumber, setCurrentNumber] = useState(savedState?.currentNumber || null);
    const [gameStatus, setGameStatus] = useState('STOPPED');
    const [checkType, setCheckType] = useState(null);
    const [speed, setSpeed] = useState(savedState?.speed || 3500);
    const [speechEnabled, setSpeechEnabled] = useState(savedState?.speechEnabled ?? true);
    const [history, setHistory] = useState(savedState?.history || []);
    const [targetPrize, setTargetPrize] = useState(savedState?.targetPrize || 'LÍNEA');
    const [hasSavedGame, setHasSavedGame] = useState(!!savedState?.drawnNumbers?.length);

    // Prize configuration for classic mode
    const [cardPrice, setCardPrice] = useState(savedState?.cardPrice || 0);
    const [cardCount, setCardCount] = useState(savedState?.cardCount || 0);
    const [lineaPercent, setLineaPercent] = useState(savedState?.lineaPercent || 30);
    const [bingoPercent, setBingoPercent] = useState(savedState?.bingoPercent || 70);
    const totalPot = cardPrice * cardCount;
    const lineaPrize = totalPot * (lineaPercent / 100);
    const bingoPrize = totalPot * (bingoPercent / 100);
    const [resultPopup, setResultPopup] = useState(null); // 'WIN' or 'FAIL'
    const [resultMessage, setResultMessage] = useState('');

    // Fiesta — verificación de reclamaciones del anfitrión único (F2)
    const [fiestaClaim, setFiestaClaim] = useState(null);   // reclamación activa entrante
    const [fiestaWinner, setFiestaWinner] = useState(null); // { apodo, tipo, premio }
    const [hostToast, setHostToast] = useState(null);       // { msg, kind }
    const [resolvingClaim, setResolvingClaim] = useState(false);

    // Check State
    const [checkMode, setCheckMode] = useState('AUTO');
    const [cardId, setCardId] = useState('');
    const [generatedCard, setGeneratedCard] = useState(null);
    const [manualNum, setManualNum] = useState('');
    const [manualCheckResult, setManualCheckResult] = useState(null);
    const [cardNotFound, setCardNotFound] = useState(false);
    const [cardLoading, setCardLoading] = useState(false);

    // Popup states for compact controls
    const [showSpeedPopup, setShowSpeedPopup] = useState(false);
    const [showVoicePopup, setShowVoicePopup] = useState(false);

    // Voice State
    const [voices, setVoices] = useState([]);
    const [selectedVoice, setSelectedVoice] = useState(null);

    const timerRef = useRef(null);
    const availableNumbersRef = useRef(availableNumbers);
    const isDrawingRef = useRef(false);
    const isSpeakingRef = useRef(false);
    const lastClaimIdRef = useRef(null); // última reclamación vista (F2)
    const synth = window.speechSynthesis;

    // Keep ref in sync with state to avoid stale closure in setInterval
    useEffect(() => {
        availableNumbersRef.current = availableNumbers;
    }, [availableNumbers]);

    // Clear speaking flag when speech is disabled to prevent blocking
    useEffect(() => {
        if (!speechEnabled) {
            synth?.cancel();
            isSpeakingRef.current = false;
        }
    }, [speechEnabled]);

    // Safe voice loading to prevent blank screen
    useEffect(() => {
        if (!synth) return;
        const loadVoices = () => {
            const available = synth.getVoices().filter(v => v.lang.startsWith('es'));
            setVoices(available);
            if (available.length > 0) {
                const premiumVoice = available.find(v => v.name.includes('Monica') || v.name.includes('Google') || v.name.includes('Premium'));
                const standardVoice = available.find(v => v.lang === 'es-ES') || available[0];
                setSelectedVoice(premiumVoice || standardVoice);
            }
        };
        loadVoices();
        if (speechSynthesis.onvoiceschanged !== undefined) { speechSynthesis.onvoiceschanged = loadVoices; }
    }, [synth]);

    // Save game state to localStorage (only in local mode, not fiesta)
    useEffect(() => {
        if (isFiestaMode) return; // Don't save fiesta mode - it syncs with server

        const stateToSave = {
            availableNumbers,
            drawnNumbers,
            currentNumber,
            history,
            speed,
            speechEnabled,
            targetPrize,
            cardPrice,
            cardCount,
            lineaPercent,
            bingoPercent,
            savedAt: new Date().toISOString()
        };

        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(stateToSave));
            setHasSavedGame(drawnNumbers.length > 0);
        } catch (e) {
            console.warn('Error saving game state:', e);
        }
    }, [availableNumbers, drawnNumbers, currentNumber, history, speed, speechEnabled, targetPrize, cardPrice, cardCount, lineaPercent, bingoPercent, isFiestaMode]);

    // Clear saved game and start fresh
    const clearSavedGame = () => {
        localStorage.removeItem(STORAGE_KEY);
        setAvailableNumbers(generateShuffledPool());
        setDrawnNumbers([]);
        setCurrentNumber(null);
        setHistory([]);
        setHasSavedGame(false);
        setGameStatus('STOPPED');
        if (timerRef.current) clearTimeout(timerRef.current);
    };

    const speakText = (text) => {
        if (!speechEnabled || !synth) return;
        synth.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'es-ES';
        utterance.rate = 1.1;
        if (selectedVoice) utterance.voice = selectedVoice;
        synth.speak(utterance);
    };

    const speakNumber = (num, onComplete) => {
        if (!speechEnabled || !synth) {
            if (onComplete) onComplete();
            return;
        }
        
        isSpeakingRef.current = true;
        synth.cancel();

        // En Android e iOS speechSynthesis a veces no dispara onend ni onerror:
        // sin este tope, el sorteo se quedaría esperando para siempre.
        let finished = false;
        const finish = () => {
            if (finished) return;
            finished = true;
            clearTimeout(safety);
            isSpeakingRef.current = false;
            if (onComplete) onComplete();
        };
        const safety = setTimeout(finish, 6000);
        
        const utterance = new SpeechSynthesisUtterance(num.toString());
        utterance.lang = 'es-ES';
        utterance.rate = speed < 2500 ? 1.2 : 1.0;
        if (selectedVoice) utterance.voice = selectedVoice;

        if (NICKNAMES[num] && speed > 2500) {
            utterance.onend = () => {
                const nick = new SpeechSynthesisUtterance(NICKNAMES[num]);
                nick.lang = 'es-ES';
                nick.pitch = 1.1;
                if (selectedVoice) nick.voice = selectedVoice;
                nick.onend = finish;
                nick.onerror = finish;
                synth.speak(nick);
            };
        } else {
            utterance.onend = finish;
        }
        
        utterance.onerror = finish;
        
        synth.speak(utterance);
    };

    const drawBallRef = useRef(null);
    
    // Draw ball function - uses refs to get current values
    drawBallRef.current = async () => {
        // Prevent concurrent draws or drawing while speaking
        if (isDrawingRef.current || isSpeakingRef.current) return;
        isDrawingRef.current = true;

        // Use ref to get current available numbers
        const currentAvailable = availableNumbersRef.current;
        if (currentAvailable.length === 0) { 
            setGameStatus('STOPPED');
            clearTimeout(timerRef.current);
            isDrawingRef.current = false; 
            return; 
        }

        let newNumber;

        // In fiesta mode, get ball from backend to sync with players
        if (isFiestaMode) {
            if (!partidaId) {
                console.warn('Fiesta mode active but no partidaId - waiting for game to start');
                isDrawingRef.current = false;
                return;
            }
            try {
                const res = await fetch(`/api/partidas/${partidaId}/sacar-bola`, { method: 'POST' });
                const data = await res.json();
                if (data.exito && data.bolaActual) {
                    newNumber = data.bolaActual;
                    if (data.bolasSacadas) {
                        setDrawnNumbers(data.bolasSacadas);
                    }
                    if (data.historial) {
                        setHistory(data.historial);
                    }
                } else {
                    console.error('Backend draw failed:', data);
                    isDrawingRef.current = false;
                    return;
                }
            } catch (err) {
                console.error('Error drawing ball from backend:', err);
                isDrawingRef.current = false;
                return;
            }
        } else {
            // Local mode - pop from available, push to drawn
            newNumber = currentAvailable[currentAvailable.length - 1];
            // Update ref immediately to prevent stale reads
            availableNumbersRef.current = currentAvailable.slice(0, -1);
            setAvailableNumbers(availableNumbersRef.current);
            setDrawnNumbers(prev => [newNumber, ...prev]);
            setHistory(h => [newNumber, ...h].slice(0, 5));
        }

        if (newNumber) {
            setCurrentNumber(newNumber);
            speakNumber(newNumber, () => {
                isDrawingRef.current = false;
            });
        } else {
            isDrawingRef.current = false;
        }
    };

    // Recursive timeout for drawing balls - ensures fresh state on each call
    useEffect(() => {
        if (gameStatus !== 'PLAYING') {
            clearTimeout(timerRef.current);
            return;
        }

        const scheduleNextDraw = () => {
            timerRef.current = setTimeout(() => {
                if (drawBallRef.current) {
                    drawBallRef.current();
                }
                scheduleNextDraw();
            }, speed);
        };

        // Initial draw if no balls yet
        if (drawnNumbers.length === 0 && !currentNumber && drawBallRef.current) {
            drawBallRef.current();
        }
        
        scheduleNextDraw();
        
        return () => clearTimeout(timerRef.current);
    }, [gameStatus, speed]);

    const togglePlayPause = () => {
        if (gameStatus === 'PLAYING') {
            setGameStatus('PAUSED');
            synth.cancel();
        } else if (drawnNumbers.length < 90) {
            setGameStatus('PLAYING');
            setCheckType(null);
            setResultPopup(null);
            if(drawnNumbers.length > 0) { speakText(`Continuamos para ${targetPrize.toLowerCase()}`); }
        }
    };

    const stopGame = () => {
        setGameStatus('STOPPED');
        clearTimeout(timerRef.current);
        setCheckType(null);
        setTargetPrize('LÍNEA');
        setResultPopup(null);
    };

    const resetGame = () => {
        if(confirm("¿Seguro que quieres salir y volver al inicio?")) {
            if (isFiestaMode && partidaId) {
                // Cierra la partida y devuelve la sala; si falla, la tarea de salas inactivas la recoge.
                fetch(`/api/partidas/${partidaId}/finalizar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ liberarSala: true }),
                }).catch(() => {});
            }
            stopGame();
            setAvailableNumbers(generateShuffledPool());
            setDrawnNumbers([]);
            setCurrentNumber(null);
            setHistory([]);
            synth.cancel();
            setFiestaPlayers([]); setIsFiestaMode(false); setPartidaId(null);
            setAppMode('LANDING');
        }
    };

    const handleCheck = (type) => {
        setGameStatus('CHECKING');
        setCheckType(type);
        setCheckMode('AUTO');
        setCardId('');
        setGeneratedCard(null);
        setManualNum('');
        setManualCheckResult(null);
        setCardNotFound(false);
        setResultPopup(null);
        synth.cancel();
        speakText(type === 'LÍNEA' ? '¡Han cantado línea!' : '¡Han cantado bingo!');
    };

    const verifyCard = async () => {
        const id = parseInt(cardId);
        if(!id || id < 1) return;

        setCardLoading(true);
        setCardNotFound(false);
        setGeneratedCard(null);

        try {
            const response = await fetch(`/api/cartones/validar/${id}`);
            if (!response.ok) {
                setCardNotFound(true);
                setCardLoading(false);
                return;
            }
            const data = await response.json();
            if (!data.existe) {
                setCardNotFound(true);
                setCardLoading(false);
                return;
            }
            const gridParsed = data.grid.map(row =>
                row.map(cell => cell === null ? null : parseInt(cell))
            );
            const flatParsed = data.flatNumbers.map(n => parseInt(n));
            setGeneratedCard({
                grid: gridParsed,
                flat: flatParsed,
                numero: data.numero
            });
        } catch (error) {
            console.error('Error validating card:', error);
            setCardNotFound(true);
        }
        setCardLoading(false);
    };

    const checkManualNumber = () => {
        const n = parseInt(manualNum);
        if(n && n >= 1 && n <= 90) {
            setManualCheckResult(drawnNumbers.includes(n) ? 'FOUND' : 'NOT_FOUND');
        }
    };

    const resumeFromCheck = () => {
        setResultPopup('FAIL');
        setResultMessage('¡Falsa Alarma!');
        playSoundEffect('FAIL');
        speakText(`¡Cartón incorrecto! Continuamos para ${targetPrize.toLowerCase()}.`);

        setTimeout(() => {
            setResultPopup(null);
            setGameStatus('PAUSED');
        }, 3000);
    };

    const confirmCheck = () => {
        confetti({ particleCount: 150, spread: 70, origin: { y: 0.6 }, disableForReducedMotion: true });
        setResultPopup('WIN');
        playSoundEffect('WIN');

        if (checkType === 'LÍNEA') {
            setResultMessage('¡LÍNEA CORRECTA!');
            speakText("¡Línea correcta! Seguimos para Bingo.");
            setTargetPrize('BINGO');
        } else if (checkType === 'BINGO') {
            setResultMessage('¡BINGO CORRECTO!');
            speakText("¡Bingo correcto! ¡Partida finalizada!");
        }

        setTimeout(() => {
            setResultPopup(null);
            setGameStatus('PAUSED');
            setCheckType(null);
        }, 4000);
    };

    const getSpeedLabel = (ms) => { if (ms >= 5000) return "Relax"; if (ms <= 2000) return "Turbo"; return "Normal"; };

    // Create room when entering HOST_LOBBY
    useEffect(() => {
        if (appMode === 'HOST_LOBBY' && !roomCode && !isCreatingRoom) {
            setIsCreatingRoom(true);
            fetch('/api/salas', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nombreSala: 'Sala de Bingo' })
            })
            .then(res => res.json())
            .then(data => {
                if (data.exito) {
                    setRoomCode(data.salaCodigo);
                    setRoomName(data.nombreSala);
                    setRoomPin(data.salaPin || '');
                    saveFiestaHost({ roomCode: data.salaCodigo, roomName: data.nombreSala, roomPin: data.salaPin || '', partidaId: null });
                }
                setIsCreatingRoom(false);
            })
            .catch(err => {
                console.error('Error creating room:', err);
                setIsCreatingRoom(false);
            });
        }
    }, [appMode, roomCode, isCreatingRoom]);

    // Generate styled QR code when room is ready
    useEffect(() => {
        if (appMode === 'HOST_LOBBY' && roomCode && roomPin && !qrCodeInstance.current) {
            const generateQR = async () => {
                if (!qrRef.current) {
                    setTimeout(generateQR, 100);
                    return;
                }

                try {
                    const baseUrl = window.location.origin;
                    const joinUrl = `${baseUrl}/fiesta/unirse?codigo=${roomCode}#pin=${roomPin}`;

                    const qr = new QRCodeStyling({
                        width: 180,
                        height: 180,
                        type: "canvas",
                        data: joinUrl,
                        image: logoB,
                        dotsOptions: {
                            color: "#000000",
                            type: "dots"
                        },
                        cornersSquareOptions: {
                            color: "#000000",
                            type: "extra-rounded"
                        },
                        cornersDotOptions: {
                            color: "#000000",
                            type: "dot"
                        },
                        backgroundOptions: {
                            color: "#ffffff"
                        },
                        imageOptions: {
                            crossOrigin: "anonymous",
                            margin: 5,
                            imageSize: 0.4
                        },
                        qrOptions: {
                            errorCorrectionLevel: "H"
                        }
                    });

                    // Create container and wait for QR to be fully rendered
                    const container = document.createElement('div');
                    container.style.display = 'flex';
                    container.style.alignItems = 'center';
                    container.style.justifyContent = 'center';

                    // Append QR to container (this triggers canvas creation)
                    qr.append(container);

                    // Wait for canvas to be ready
                    await new Promise(resolve => {
                        const checkCanvas = () => {
                            const canvas = container.querySelector('canvas');
                            if (canvas && canvas.width > 0) {
                                resolve();
                            } else {
                                requestAnimationFrame(checkCanvas);
                            }
                        };
                        checkCanvas();
                    });

                    // Now clear and append - QR is fully ready
                    while (qrRef.current && qrRef.current.firstChild) {
                        qrRef.current.removeChild(qrRef.current.firstChild);
                    }
                    if (qrRef.current) {
                        qrRef.current.appendChild(container);
                    }
                    qrCodeInstance.current = qr;
                    setQrReady(true);
                } catch (err) {
                    console.error('Error generating QR:', err);
                    setQrReady(true);
                }
            };

            setTimeout(generateQR, 100);
        }
    }, [appMode, roomCode, roomPin]);

    // Poll for players when in lobby
    useEffect(() => {
        if (appMode === 'HOST_LOBBY' && roomCode) {
            const pollPlayers = () => {
                fetch(`/api/salas/${roomCode}/lobby`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.exito && data.jugadores) {
                            setPlayers(data.jugadores.map(j => j.apodo));
                        }
                    })
                    .catch(err => console.error('Error polling players:', err));
            };

            pollPlayers(); // Initial fetch
            const interval = setInterval(pollPlayers, 2000);
            return () => clearInterval(interval);
        }
    }, [appMode, roomCode]);

    // --- FIESTA: anfitrión único, verificación humana de reclamaciones (F2) ---
    // Sondea el estado de la partida para recibir reclamaciones entrantes y el ganador.
    useEffect(() => {
        if (!isFiestaMode || !partidaId) return;
        let etag = null;
        let cancelled = false;

        const poll = async () => {
            try {
                const headers = etag ? { 'If-None-Match': etag } : {};
                const res = await fetch(`/api/partidas/${partidaId}/estado`, { headers });
                if (res.status === 304) return;
                etag = res.headers.get('ETag');
                const data = await res.json();
                if (cancelled || !data.exito) return;

                if (data.ganador) setFiestaWinner(data.ganador);

                const incoming = data.reclamacionActiva || null;
                const incomingId = incoming ? incoming.reclamacionId : null;
                if (incomingId && incomingId !== lastClaimIdRef.current) {
                    // Nueva reclamación: pausar el sorteo y abrir el modal de verificación.
                    clearTimeout(timerRef.current);
                    setGameStatus('PAUSED');
                    synth?.cancel();
                }
                lastClaimIdRef.current = incomingId;
                setFiestaClaim(incoming);
            } catch (e) {
                // Silenciar errores de sondeo; se reintenta en el próximo ciclo.
            }
        };

        poll();
        const id = setInterval(poll, 1500);
        return () => { cancelled = true; clearInterval(id); };
    }, [isFiestaMode, partidaId]);

    // Auto-ocultar el toast del anfitrión.
    useEffect(() => {
        if (!hostToast) return;
        const id = setTimeout(() => setHostToast(null), 3600);
        return () => clearTimeout(id);
    }, [hostToast]);

    const resolveClaim = async (accion) => {
        if (!fiestaClaim || resolvingClaim) return;
        setResolvingClaim(true);
        const { reclamacionId, tipo, apodo } = fiestaClaim;
        try {
            const res = await fetch(`/api/reclamaciones/${reclamacionId}/${accion}`, { method: 'POST' });
            const data = await res.json();
            if (!data.exito) {
                setHostToast({ msg: data.error || 'No se pudo procesar la reclamación', kind: 'error' });
                setResolvingClaim(false);
                return;
            }

            if (accion === 'aprobar' && tipo === 'BINGO') {
                confetti({ particleCount: 200, spread: 80, origin: { y: 0.6 }, disableForReducedMotion: true });
                playSoundEffect('WIN');
                speakText(`¡Bingo correcto! ${apodo} ha ganado. Partida finalizada.`);
                setFiestaWinner(prev => prev || { apodo, tipo: 'BINGO', premio: null });
            } else if (accion === 'aprobar') {
                playSoundEffect('WIN');
                speakText(`¡Línea correcta de ${apodo}! Seguimos para bingo.`);
                setTargetPrize('BINGO');
                setHostToast({ msg: `Línea de ${apodo} aprobada · seguimos para bingo`, kind: 'success' });
                setGameStatus('PLAYING');
            } else {
                playSoundEffect('FAIL');
                speakText(`Cartón incorrecto de ${apodo}. Seguimos jugando.`);
                setHostToast({ msg: `Reclamación de ${apodo} rechazada · falsa alarma`, kind: 'error' });
                setGameStatus('PLAYING');
            }

            setFiestaClaim(null);
            lastClaimIdRef.current = null;
        } catch (e) {
            setHostToast({ msg: 'Error de conexión al resolver la reclamación', kind: 'error' });
        }
        setResolvingClaim(false);
    };

    const nuevaPartidaTrasGanador = async () => {
        try {
            if (partidaId) {
                await fetch(`/api/partidas/${partidaId}/finalizar`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ liberarSala: true }),
                });
            }
        } catch (e) {
            // Continuar igualmente al menú de inicio.
        }
        setFiestaWinner(null);
        setFiestaClaim(null);
        lastClaimIdRef.current = null;
        setIsFiestaMode(false);
        setPartidaId(null);
        setFiestaPlayers([]);
        stopGame();
        setAvailableNumbers(generateShuffledPool());
        setDrawnNumbers([]);
        setCurrentNumber(null);
        setHistory([]);
        setAppMode('LANDING');
    };

    // Volver al inicio cierra la sesión de Modo Fiesta guardada.
    useEffect(() => {
        if (appMode === 'LANDING') saveFiestaHost(null);
    }, [appMode]);

    // Tras recargar: comprueba que la sala sigue siendo nuestra y recupera la partida en pausa.
    useEffect(() => {
        if (!savedFiesta) return;
        let cancelled = false;

        const abandon = () => {
            saveFiestaHost(null);
            setIsFiestaMode(false);
            setPartidaId(null);
            setAppMode('LANDING');
        };

        (async () => {
            try {
                const lobbyRes = await fetch(`/api/salas/${savedFiesta.roomCode}/lobby`);
                if (cancelled) return;
                if (lobbyRes.status === 404) { abandon(); return; }
                if (!restoringGame || !lobbyRes.ok) return;

                const lobby = await lobbyRes.json();
                setFiestaPlayers((lobby.jugadores || []).map(j => j.apodo));

                const res = await fetch(`/api/partidas/${savedFiesta.partidaId}/estado`);
                if (cancelled) return;
                if (res.status === 404) { abandon(); return; }
                const data = await res.json();
                if (cancelled || !data.exito) return;
                // Finalizada sin ganador: el anfitrión ya la había cerrado.
                if (data.estadoPartida === 'FINALIZADA' && !data.ganador) { abandon(); return; }

                setDrawnNumbers(data.bolasSacadas || []);
                setHistory(data.historial || []);
                setCurrentNumber(data.bolaActual || null);
                setTargetPrize(data.premioObjetivo === 'LINEA' ? 'LÍNEA' : 'BINGO');
                setGameStatus('PAUSED');
            } catch (e) {
                // Sin red: se queda en pantalla y el sondeo del estado se pondrá al día al volver la conexión.
            }
        })();

        return () => { cancelled = true; };
    }, []);

    useWakeLock(appMode === 'SOLO' && gameStatus !== 'STOPPED');

    // Escape cierra los desplegables de velocidad y voz.
    useEffect(() => {
        if (!showSpeedPopup && !showVoicePopup) return;
        const onKey = (e) => {
            if (e.key === 'Escape') { setShowSpeedPopup(false); setShowVoicePopup(false); }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [showSpeedPopup, showVoicePopup]);

    // Reset room state when leaving lobby
    useEffect(() => {
        if (appMode !== 'HOST_LOBBY') {
            setRoomCode('');
            setRoomName('');
            setRoomPin('');
            setPlayers([]);
            setQrReady(false);
            qrCodeInstance.current = null;
            setIsCreatingRoom(false);
        }
    }, [appMode]);

    // --- CHECKER HELPERS ---
    // Check rows for lines
    const getRowStatus = (rowNums) => {
        if (!rowNums) return null;
        const filledCount = rowNums.filter(n => n !== null && drawnNumbers.includes(n)).length;
        const totalNums = rowNums.filter(n => n !== null).length;
        if (totalNums === 0) return 'EMPTY';
        return filledCount === totalNums ? 'COMPLETE' : 'INCOMPLETE';
    };

    const isBingo = (grid) => {
        return grid.every(row => getRowStatus(row) === 'COMPLETE' || getRowStatus(row) === 'EMPTY');
    };

    // --- RENDER ---

    if (appMode === 'LANDING') {
        return (
            <div className="relative w-screen h-screen flex flex-col overflow-hidden">
                <div className="aurora-bg"><div className="blob blob-1"></div><div className="blob blob-2"></div><div className="blob blob-3"></div></div>
                <BetaBadge />
                <LandingPage onSelectMode={(mode) => setAppMode(mode === 'SOLO' ? 'SOLO' : 'HOST_LOBBY')} />
            </div>
        );
    }

    const copyJoinUrl = () => {
        const baseUrl = window.location.origin;
        const joinUrl = `${baseUrl}/fiesta/unirse?codigo=${roomCode}#pin=${roomPin}`;
        navigator.clipboard.writeText(joinUrl).then(() => {
            setUrlCopied(true);
            setTimeout(() => setUrlCopied(false), 2000);
        });
    };

    // Salir de la sala de espera la devuelve al pool para el siguiente anfitrión.
    const leaveLobby = () => {
        if (roomCode) {
            fetch(`/api/salas/${roomCode}/liberar`, { method: 'POST' }).catch(() => {});
        }
        setAppMode('LANDING');
    };

    const startFiestaGame = async () => {
        if (isStartingGame) return;
        setIsStartingGame(true);

        try {
            // 1. Create game in backend
            const createRes = await fetch(`/api/salas/${roomCode}/partidas`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    premioObjetivo: 'BINGO',
                    precioCarton: 0,
                    porcentajeLinea: 30,
                    porcentajeBingo: 70
                })
            });
            const createData = await createRes.json();

            if (!createData.exito) {
                console.error('Error creating game:', createData);
                setIsStartingGame(false);
                return;
            }

            const gameId = createData.partidaId;
            setPartidaId(gameId);
            saveFiestaHost({ roomCode, roomName, roomPin, partidaId: gameId });

            // 2. Start the game
            const startRes = await fetch(`/api/partidas/${gameId}/comenzar`, {
                method: 'POST'
            });
            const startData = await startRes.json();

            if (!startData.exito) {
                console.error('Error starting game:', startData);
                setIsStartingGame(false);
                return;
            }

            // 3. Switch to game mode - clear any saved local state first
            setDrawnNumbers([]);
            setCurrentNumber(null);
            setHistory([]);
            setFiestaPlayers([...players]);
            setIsFiestaMode(true);
            setAppMode('SOLO');
            setIsStartingGame(false);
        } catch (err) {
            console.error('Error in startFiestaGame:', err);
            setIsStartingGame(false);
        }
    };

    if (appMode === 'HOST_LOBBY') {
        if (isCreatingRoom || !roomCode) {
            return (
                <div className="relative w-screen h-screen flex flex-col items-center justify-center overflow-hidden">
                    <div className="aurora-bg"><div className="blob blob-1"></div><div className="blob blob-2"></div><div className="blob blob-3"></div></div>
                    <BetaBadge />
                    <div className="glass-panel p-12 rounded-3xl text-center animate-pulse">
                        <div className="w-16 h-16 border-4 border-pink-500 border-t-transparent rounded-full animate-spin mx-auto mb-6"></div>
                        <h2 className="text-2xl font-bold text-white mb-2">Creando sala...</h2>
                        <p className="text-white/50">Por favor espera un momento</p>
                    </div>
                </div>
            );
        }

        return (
            <div className="relative w-screen h-screen flex flex-col p-4 md:p-8 items-center justify-center overflow-hidden">
                <div className="aurora-bg"><div className="blob blob-1"></div><div className="blob blob-2"></div><div className="blob blob-3"></div></div>
                <BetaBadge />

                <div className="glass-panel p-6 md:p-12 rounded-3xl w-full max-w-5xl text-center relative animate-enter">
                    <button onClick={leaveLobby} className="absolute top-4 left-4 md:top-6 md:left-6 text-white/50 hover:text-white flex items-center gap-2">
                        <Icons.RotateCcw className="w-4 h-4" /> Volver
                    </button>

                    <h2 className="text-lg md:text-2xl font-bold text-pink-300 uppercase tracking-widest mb-2">Sala Privada</h2>
                    <div className="text-4xl md:text-8xl font-black text-white tracking-tighter mb-2 bg-clip-text text-transparent bg-gradient-to-r from-white to-white/70">
                        {roomCode}
                    </div>

                    {roomPin && (
                        <div className="mb-4">
                            <span className="text-white/50 text-sm uppercase tracking-wider">PIN de acceso: </span>
                            <span className="font-mono text-2xl md:text-4xl font-black text-yellow-400 tracking-widest">{roomPin}</span>
                        </div>
                    )}

                    <button
                        onClick={copyJoinUrl}
                        className={`mb-6 inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium transition-all ${urlCopied ? 'bg-green-500/20 text-green-300 border border-green-500/30' : 'bg-white/10 text-white/70 hover:bg-white/20 hover:text-white border border-white/10'}`}
                    >
                        {urlCopied ? <Icons.Check className="w-4 h-4" /> : <Icons.Copy className="w-4 h-4" />}
                        {urlCopied ? 'Copiado!' : 'Copiar enlace para compartir'}
                    </button>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-12 mb-8 md:mb-12">
                        <div className="flex flex-col items-center">
                            <div className="bg-white p-3 md:p-4 rounded-2xl w-52 h-52 md:w-60 md:h-60 flex items-center justify-center shadow-2xl relative overflow-hidden">
                                {!qrReady && (
                                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-white z-10">
                                        <div className="w-12 h-12 border-4 border-pink-500 border-t-transparent rounded-full animate-spin"></div>
                                        <span className="text-gray-500 text-sm font-medium">Generando QR...</span>
                                    </div>
                                )}
                                <div ref={qrRef} className="w-full h-full flex items-center justify-center"></div>
                            </div>
                            <p className="text-white/55 text-xs mt-3">Escanea para unirte</p>
                        </div>
                        <div className="text-left">
                            <h3 className="text-white/50 uppercase tracking-widest text-sm font-bold mb-4">Jugadores ({players.length})</h3>
                            <div className="flex flex-wrap gap-2 max-h-40 overflow-y-auto">
                                {players.map((p) => (
                                    <div key={p} className="glass px-4 py-2 rounded-full flex items-center gap-2 animate-enter">
                                        <div className="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
                                        {p}
                                    </div>
                                ))}
                                {players.length === 0 && <span className="text-white/55 italic">Esperando jugadores...</span>}
                            </div>
                        </div>
                    </div>

                    <button
                        onClick={startFiestaGame}
                        disabled={players.length === 0 || isStartingGame}
                        className={`bg-gradient-to-r from-[#8b5cf6] via-[#ec4899] to-[#22d3ee] px-8 md:px-12 py-4 md:py-6 rounded-2xl font-black text-xl md:text-2xl shadow-2xl transition-transform ${players.length === 0 || isStartingGame ? 'opacity-50 cursor-not-allowed' : 'hover:scale-105'}`}
                    >
                        {isStartingGame ? (
                            <span className="flex items-center gap-3">
                                <div className="w-6 h-6 border-3 border-white border-t-transparent rounded-full animate-spin"></div>
                                Iniciando...
                            </span>
                        ) : 'EMPEZAR PARTIDA'}
                    </button>
                    {players.length === 0 && (
                        <p className="text-white/50 mt-4 text-sm">Espera a que se unan jugadores para comenzar</p>
                    )}
                </div>
            </div>
        );
    }

    // Datos derivados para el modal de reclamación del anfitrión (F2)
    const claimMarked = new Set(fiestaClaim?.numerosMarcados || []);
    const claimWinningRow = fiestaClaim && fiestaClaim.tipo === 'LINEA'
        ? (fiestaClaim.cuadricula || []).findIndex(row => {
            const nums = row.filter(n => n);
            return nums.length > 0 && nums.every(n => claimMarked.has(n));
          })
        : -1;

    // GAME VIEW (Shared for SOLO and HOST GAME for now)
    return (
        <div className="relative w-full h-full flex flex-col p-2 md:p-6 lg:p-8 overflow-hidden safe-area-inset">
            <div className="aurora-bg"><div className="blob blob-1"></div><div className="blob blob-2"></div><div className="blob blob-3"></div></div>
            <BetaBadge />

            {/* Navbar - Compact on mobile */}
            <nav className="glass rounded-xl md:rounded-2xl p-2 md:p-4 flex justify-between items-center mb-2 md:mb-6 z-50 flex-shrink-0">
                <div className="flex items-center gap-2 md:gap-6">
                    <div className="flex items-center gap-2 md:gap-3">
                        <BingoLogo className="w-8 h-8 md:w-12 md:h-12" />
                        <div className="hidden sm:block">
                            <h1 className="text-sm md:text-lg font-black leading-none tracking-tight">BINGOENCASA<span className="opacity-50 font-normal">.ES</span></h1>
                        </div>
                    </div>
                    {isFiestaMode && fiestaPlayers.length > 0 && (
                        <div className="hidden md:flex items-center gap-2">
                            <Icons.Users className="w-4 h-4 text-pink-300" />
                            <div className="flex gap-1">
                                {fiestaPlayers.slice(0, 4).map((p) => (
                                    <span key={p} className="glass px-2 py-1 rounded-full text-xs flex items-center gap-1">
                                        <span className="w-1.5 h-1.5 bg-green-400 rounded-full"></span>
                                        {p}
                                    </span>
                                ))}
                                {fiestaPlayers.length > 4 && <span className="text-white/50 text-xs">+{fiestaPlayers.length - 4}</span>}
                            </div>
                        </div>
                    )}
                    <div className="hidden lg:flex items-center gap-2 opacity-80">
                        <span className="text-[9px] uppercase tracking-widest text-white/55">Creado por</span>
                        <img src="/assets/Thrax.es.svg" alt="Thrax.es" className="h-4 w-auto" />
                    </div>
                </div>
                <div className="flex items-center gap-1 md:gap-3">
                    <StatusBadge status={gameStatus} />
                    <div className="flex gap-1 md:gap-2">
                        <button onClick={() => setSpeechEnabled(!speechEnabled)} aria-label="Voz del bombo" aria-pressed={speechEnabled} className="p-2 md:p-2 rounded-full hover:bg-white/10 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center">{speechEnabled ? <Icons.Volume2 className="w-5 h-5" /> : <Icons.VolumeX className="w-5 h-5 text-white/50" />}</button>
                        <button onClick={resetGame} className="p-2 md:p-2 rounded-full hover:bg-red-500/20 text-red-300 transition-colors min-h-[44px] min-w-[44px] flex items-center justify-center" title="Salir al menú" aria-label="Salir al menú"><Icons.RotateCcw className="w-5 h-5" /></button>
                    </div>
                </div>
            </nav>

            {/* Mobile Tab Navigation - Only visible on small screens */}
            <div className="lg:hidden glass rounded-xl p-1 mb-2 flex gap-1 z-40 flex-shrink-0">
                <button
                    onClick={() => setMobileTab('controls')}
                    className={`flex-1 py-3 px-4 rounded-lg font-bold text-sm flex items-center justify-center gap-2 transition-all min-h-[48px] ${mobileTab === 'controls' ? 'bg-white/20 text-white' : 'text-white/50 hover:text-white hover:bg-white/5'}`}
                >
                    <Icons.Play className="w-5 h-5" /> Controles
                </button>
                <button
                    onClick={() => setMobileTab('board')}
                    className={`flex-1 py-3 px-4 rounded-lg font-bold text-sm flex items-center justify-center gap-2 transition-all min-h-[48px] ${mobileTab === 'board' ? 'bg-white/20 text-white' : 'text-white/50 hover:text-white hover:bg-white/5'}`}
                >
                    <TabIcons.Board className="w-5 h-5" /> Tablero
                </button>
            </div>

            <main className="flex-1 flex flex-col lg:flex-row gap-2 md:gap-6 relative z-10 pb-2 md:pb-4 overflow-hidden min-h-0">
                {/* Controls Panel - Hidden on mobile when board tab is active */}
                <div className={`lg:w-[380px] xl:w-[420px] 2xl:w-[480px] flex flex-col gap-2 md:gap-4 overflow-hidden min-h-0 flex-shrink-0 ${mobileTab === 'board' ? 'hidden lg:flex' : 'flex'}`}>

                    {/* Main Display Stage - fixed 55% height */}
                    <div className="glass-panel rounded-2xl md:rounded-3xl p-3 md:p-6 flex flex-col items-center justify-center relative h-[55%] flex-shrink-0 overflow-hidden transition-all">

                        {/* RESULTS POPUP OVERLAY */}
                        {resultPopup && (
                            <div role="status" className="absolute inset-0 z-50 bg-black/90 backdrop-blur-xl flex flex-col items-center justify-center animate-slide-up text-center p-8">
                                <div className={`text-6xl mb-6 ${resultPopup === 'WIN' ? 'animate-bounce' : 'animate-pulse'}`}>
                                    {resultPopup === 'WIN' ? '🏆' : '🚫'}
                                </div>
                                <h1 className={`text-4xl md:text-6xl font-black uppercase tracking-tighter mb-4 ${resultPopup === 'WIN' ? 'text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 to-orange-500' : 'text-red-500'}`}>
                                    {resultMessage}
                                </h1>
                                {resultPopup === 'FAIL' && <p className="text-white/50 text-xl">Continuamos jugando...</p>}
                            </div>
                        )}

                        {gameStatus === 'CHECKING' && !resultPopup ? (
                            <div className="absolute inset-0 z-30 bg-black/90 backdrop-blur-xl flex flex-col animate-enter p-4 md:p-6 overflow-y-auto">
                                <div className="flex items-center justify-between w-full mb-4 md:mb-6">
                                    <div className="flex items-center gap-3">
                                        <div className={`p-2 md:p-3 rounded-xl ${checkType === 'BINGO' ? 'bg-yellow-500/20 text-yellow-300' : 'bg-cyan-500/20 text-cyan-300'}`}>
                                            {checkType === 'BINGO' ? <Icons.Trophy className="w-5 h-5 md:w-6 md:h-6" /> : <Icons.List className="w-5 h-5 md:w-6 md:h-6" />}
                                        </div>
                                        <div><h2 className="text-lg md:text-xl font-bold uppercase tracking-tight">Verificar {checkType}</h2></div>
                                    </div>
                                    <div className="flex bg-white/5 rounded-lg p-1">
                                        <button onClick={() => setCheckMode('AUTO')} className={`px-2 py-1 md:px-3 md:py-1 text-[10px] md:text-xs font-bold rounded-md transition-all ${checkMode === 'AUTO' ? 'bg-indigo-500 text-white shadow-lg' : 'text-white/60 hover:text-white'}`}>Auto</button>
                                        <button onClick={() => setCheckMode('MANUAL')} className={`px-2 py-1 md:px-3 md:py-1 text-[10px] md:text-xs font-bold rounded-md transition-all ${checkMode === 'MANUAL' ? 'bg-pink-500 text-white shadow-lg' : 'text-white/60 hover:text-white'}`}>Manual</button>
                                    </div>
                                </div>

                                {checkMode === 'AUTO' ? (
                                    <div className="flex flex-col items-center w-full max-w-md mx-auto">
                                        <div className="flex gap-2 w-full mb-6">
                                            <input type="number" aria-label="Número de cartón" placeholder="Nº Cartón (ej: 1234)" className="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-base md:text-lg font-mono focus:outline-none focus:border-indigo-500 transition-colors text-center placeholder:text-white/45" value={cardId} onChange={(e) => { setCardId(e.target.value); setCardNotFound(false); }} onKeyDown={(e) => e.key === 'Enter' && verifyCard()} />
                                            <GlassButton onClick={verifyCard} disabled={!cardId || cardLoading} variant="primary" aria-label="Comprobar cartón">
                                                {cardLoading ? <span className="animate-spin">⏳</span> : <Icons.Search className="w-5 h-5" />}
                                            </GlassButton>
                                        </div>
                                        {cardNotFound && (
                                            <div className="w-full p-4 rounded-xl bg-red-500/20 border border-red-500/30 text-red-300 text-center mb-6 animate-enter">
                                                <div className="flex items-center justify-center gap-2 font-bold text-lg mb-1">
                                                    <Icons.X className="w-6 h-6" /> ¡Cartón No Existe!
                                                </div>
                                                <p className="text-sm text-red-300/70">El cartón #{cardId} no está registrado en el sistema.</p>
                                            </div>
                                        )}
                                        {generatedCard && (
                                            <div className="w-full bg-white/5 rounded-xl p-4 mb-6 border border-white/10 animate-enter">
                                                <div className="flex flex-col gap-2 mb-4">
                                                    {generatedCard.grid.map((row, rIdx) => {
                                                        const rowStatus = getRowStatus(row);
                                                        return (
                                                            <div key={rIdx} className={`grid grid-cols-9 gap-1 p-1 rounded-lg ${rowStatus === 'COMPLETE' ? 'bg-green-500/20 border border-green-500/50' : 'bg-black/20'}`}>
                                                                {row.map((num, cIdx) => (
                                                                    <div key={cIdx} className={`aspect-[4/3] rounded flex items-center justify-center text-sm font-bold ${num === null ? 'bg-transparent' : drawnNumbers.includes(num) ? 'bg-green-500 text-black' : 'bg-white/10 text-white/50'}`}>
                                                                        {num || ''}
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        )
                                                    })}
                                                </div>
                                                <div className="text-center">
                                                    {isBingo(generatedCard.grid) ? (
                                                        <div className="text-green-400 font-bold text-lg animate-pulse">¡BINGO CORRECTO! 🎉</div>
                                                    ) : generatedCard.grid.some(r => getRowStatus(r) === 'COMPLETE') ? (
                                                        <div className="text-cyan-400 font-bold text-lg">¡LÍNEA CORRECTA! ✅</div>
                                                    ) : (
                                                        <div className="text-white/50 text-xs">Faltan números</div>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center w-full max-w-md mx-auto">
                                        <p className="text-white/60 text-xs md:text-sm mb-4 text-center">Introduce los números.</p>
                                        <div className="flex gap-2 w-full mb-6">
                                            <input type="number" aria-label="Número a comprobar" placeholder="Número..." className="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-base md:text-lg font-mono focus:outline-none focus:border-pink-500 transition-colors text-center placeholder:text-white/45" value={manualNum} onChange={(e) => { setManualNum(e.target.value); setManualCheckResult(null); }} onKeyDown={(e) => e.key === 'Enter' && checkManualNumber()} />
                                            <GlassButton onClick={checkManualNumber} disabled={!manualNum} variant="primary" aria-label="Comprobar número"><Icons.Check className="w-5 h-5" /></GlassButton>
                                        </div>
                                        {manualCheckResult && (<div className={`w-full p-4 rounded-xl flex items-center justify-center gap-3 mb-6 font-bold text-lg ${manualCheckResult === 'FOUND' ? 'bg-green-500/20 text-green-300 border border-green-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30'}`}>{manualCheckResult === 'FOUND' ? (<><Icons.Check className="w-6 h-6" /> ¡SÍ!</>) : (<><Icons.X className="w-6 h-6" /> ¡NO!</>)}</div>)}
                                    </div>
                                )}
                                <div className="mt-auto flex gap-3 w-full">
                                    <GlassButton variant="danger" className="flex-1 text-sm md:text-base" onClick={resumeFromCheck}>Falsa Alarma</GlassButton>
                                    <GlassButton variant="success" className="flex-1 text-sm md:text-base" onClick={confirmCheck}>Confirmar</GlassButton>
                                </div>
                            </div>
                        ) : (
                            <>
                                <div className="absolute top-2 left-3 right-3 sm:top-3 sm:left-4 sm:right-4 md:top-4 md:left-6 md:right-6 flex justify-between text-[9px] sm:text-[10px] md:text-xs font-medium uppercase tracking-widest text-white/55">
                                    <span>Última bola</span><span>{drawnNumbers.length} / 90</span>
                                </div>
                                <div className="relative z-10 flex flex-col items-center justify-center flex-1 py-2 sm:py-3 md:py-4">
                                    {/* Lo que oye un lector de pantalla cuando sale una bola. */}
                                    <p className="sr-only" aria-live="polite">{currentNumber ? `Bola ${currentNumber}` : ''}</p>
                                    {currentNumber ? (
                                        <div className="flex flex-col items-center gap-1 sm:gap-1.5 md:gap-2">
                                            <div className="drop-shadow-2xl filter transform transition-transform duration-300">
                                                <Ball3D number={currentNumber} size="xl" animate={true} />
                                            </div>
                                            <div className="text-center min-h-[16px] sm:min-h-[18px] md:min-h-[22px]">
                                                {NICKNAMES[currentNumber] && <p className="text-[10px] sm:text-xs md:text-sm lg:text-base text-pink-300 font-medium animate-pulse-slow">"{NICKNAMES[currentNumber]}"</p>}
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-center opacity-40">
                                            <div className="w-14 h-14 sm:w-16 sm:h-16 md:w-20 md:h-20 rounded-full border-2 border-dashed border-white/30 animate-spin-slow mx-auto mb-1 sm:mb-2 md:mb-3 flex items-center justify-center">
                                                <Icons.Play className="w-5 h-5 sm:w-6 sm:h-6 md:w-8 md:h-8 ml-1" />
                                            </div>
                                            <p className="text-[10px] sm:text-xs md:text-sm font-light">Esperando sorteo...</p>
                                        </div>
                                    )}
                                </div>
                                <div className="flex justify-center gap-1 sm:gap-2 md:gap-3 px-2 sm:px-4 md:px-6 pb-3 sm:pb-4 md:pb-6 flex-shrink-0">
                                    {history.slice(1).map((n) => (<div key={n} className="opacity-60 transform transition-all hover:scale-110 hover:opacity-100"><Ball3D number={n} size="sm" /></div>))}
                                </div>
                            </>
                        )}
                    </div>

                    {/* Control Panel - grows to fill remaining space */}
                    <div className="glass rounded-2xl md:rounded-3xl p-5 md:p-6 flex-1 flex flex-col">
                        {/* Header row with title, inputs, and icons */}
                        <div className="flex flex-wrap items-center gap-3 mb-4">
                            <div className="text-xs md:text-sm font-semibold uppercase tracking-wider text-white/60">Control de Sala</div>
                            <div className="flex items-center gap-2 text-xs font-mono bg-black/20 px-2.5 py-1 rounded ml-auto"><span>{Math.round(speed/1000)}s</span><span className={`w-2 h-2 rounded-full ${speed < 2500 ? 'bg-pink-500' : 'bg-green-500'}`}></span></div>
                            {!isFiestaMode && (
                                <div className="flex items-center gap-3 flex-wrap">
                                    <div className="flex items-center gap-1.5">
                                        <label className="text-[10px] text-white/50">Cartones</label>
                                        <input type="number" min="0" value={cardCount} onChange={(e) => setCardCount(Math.max(0, parseInt(e.target.value) || 0))} className="w-14 bg-white/5 border border-white/10 rounded px-2 py-1 text-sm focus:outline-none focus:border-pink-500" />
                                    </div>
                                    <div className="flex items-center gap-1.5">
                                        <label className="text-[10px] text-white/50">Precio</label>
                                        <input type="number" min="0" step="0.5" value={cardPrice} onChange={(e) => setCardPrice(Math.max(0, parseFloat(e.target.value) || 0))} className="w-14 bg-white/5 border border-white/10 rounded px-2 py-1 text-sm focus:outline-none focus:border-pink-500" />
                                    </div>
                                    <div className="relative">
                                        <button onClick={() => { setShowSpeedPopup(!showSpeedPopup); setShowVoicePopup(false); }} className="w-8 h-8 flex items-center justify-center bg-white/5 hover:bg-white/10 rounded-lg transition" title="Velocidad" aria-label="Velocidad del sorteo" aria-haspopup="true" aria-expanded={showSpeedPopup}>
                                            <Icons.Gauge className="w-5 h-5 text-white/60" />
                                        </button>
                                        {showSpeedPopup && (
                                            <div className="absolute top-full right-0 mt-1 w-40 bg-slate-900 border border-white/10 rounded-lg p-3 shadow-xl z-50">
                                                <div className="text-[10px] text-white/50 mb-2 text-center">{getSpeedLabel(speed)}</div>
                                                <input type="range" aria-label="Segundos entre bolas" min="1500" max="6000" step="500" value={speed} onChange={(e) => setSpeed(Number(e.target.value))} className="w-full accent-white h-2" />
                                            </div>
                                        )}
                                    </div>
                                    {voices.length > 0 && (
                                        <div className="relative">
                                            <button onClick={() => { setShowVoicePopup(!showVoicePopup); setShowSpeedPopup(false); }} className="w-8 h-8 flex items-center justify-center bg-white/5 hover:bg-white/10 rounded-lg transition" title="Voz" aria-label="Elegir voz" aria-haspopup="true" aria-expanded={showVoicePopup}>
                                                <Icons.Mic className="w-5 h-5 text-white/60" />
                                            </button>
                                            {showVoicePopup && (
                                                <div className="absolute top-full right-0 mt-1 w-48 bg-slate-900 border border-white/10 rounded-lg p-2 shadow-xl z-50 max-h-36 overflow-y-auto">
                                                    {voices.map(v => (
                                                        <button key={v.name} onClick={() => { setSelectedVoice(v); setShowVoicePopup(false); }} className={`w-full text-left px-2.5 py-1.5 text-[10px] rounded hover:bg-white/10 transition ${selectedVoice?.name === v.name ? 'bg-white/10 text-white' : 'text-white/70'}`}>
                                                            {v.name.replace('Microsoft', '').replace('Google', '').trim()}
                                                        </button>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                        <div className="flex flex-col gap-2">
                            <div className="flex gap-2">
                                <GlassButton onClick={togglePlayPause} active={gameStatus === 'PLAYING'} disabled={drawnNumbers.length >= 90 || gameStatus === 'CHECKING'} className="flex-1 min-h-[44px] md:min-h-[48px] text-sm md:text-base">
                                    {gameStatus === 'PLAYING' ? <Icons.Pause className="w-5 h-5" /> : <Icons.Play className="w-5 h-5" />}
                                    {gameStatus === 'PLAYING' ? 'PAUSAR' : drawnNumbers.length > 0 ? 'CONTINUAR' : 'EMPEZAR'}
                                </GlassButton>
                                {hasSavedGame && !isFiestaMode && (
                                    <GlassButton onClick={clearSavedGame} className="min-h-[44px] md:min-h-[48px] px-3" title="Nueva Partida" aria-label="Nueva partida">
                                        <Icons.RotateCcw className="w-4 h-4" />
                                    </GlassButton>
                                )}
                            </div>
                            {!isFiestaMode && (
                                <div className={`text-[10px] text-center text-green-400/70 -mt-1 ${hasSavedGame && gameStatus === 'STOPPED' ? 'visible' : 'invisible'}`}>
                                    Guardada ({drawnNumbers.length} bolas)
                                </div>
                            )}
                            {isFiestaMode ? (
                                <div className="flex items-center justify-center gap-2 py-2.5 px-3 rounded-lg bg-white/5 text-white/50 text-xs text-center">
                                    <Icons.Users className="w-4 h-4 flex-shrink-0" />
                                    <span>Los jugadores cantan línea y bingo desde su móvil. Tú las verificas aquí.</span>
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 gap-2">
                                    <GlassButton variant="line" onClick={() => handleCheck('LÍNEA')} disabled={gameStatus === 'CHECKING' || drawnNumbers.length === 0} className="min-h-[44px] md:min-h-[48px] text-sm md:text-base tracking-tight"><Icons.List className="w-5 h-5" /><span>¡LÍNEA!</span></GlassButton>
                                    <GlassButton variant="bingo" onClick={() => handleCheck('BINGO')} disabled={gameStatus === 'CHECKING' || drawnNumbers.length === 0} className="min-h-[44px] md:min-h-[48px] text-sm md:text-base tracking-tight"><Icons.Trophy className="w-5 h-5" /><span>¡BINGO!</span></GlassButton>
                                </div>
                            )}
                            {!isFiestaMode && (
                                <div className="bg-black/20 rounded-lg p-2">
                                    <div className="flex items-center gap-2">
                                        <span className="text-[10px] text-cyan-400 font-bold w-8">{lineaPercent}%</span>
                                        <input type="range" min="0" max="100" value={lineaPercent} onChange={(e) => { const v = parseInt(e.target.value); setLineaPercent(v); setBingoPercent(100 - v); }} className="flex-1 h-2 accent-cyan-400" style={{background: `linear-gradient(to right, #22d3ee ${lineaPercent}%, #eab308 ${lineaPercent}%)`}} />
                                        <span className="text-[10px] text-yellow-400 font-bold w-8 text-right">{bingoPercent}%</span>
                                    </div>
                                    <div className="flex justify-between text-[9px] text-white/55 mt-0.5">
                                        <span>Línea</span>
                                        <span>Bingo</span>
                                    </div>
                                    {totalPot > 0 && (
                                        <div className="grid grid-cols-3 gap-1 text-center text-[10px] pt-1.5 mt-1.5 border-t border-white/10">
                                            <div><span className="text-cyan-400 font-bold">{lineaPrize.toFixed(1)}</span> <span className="text-white/55">Línea</span></div>
                                            <div><span className="text-yellow-400 font-bold">{bingoPrize.toFixed(1)}</span> <span className="text-white/55">Bingo</span></div>
                                            <div><span className="text-green-400 font-bold">{totalPot.toFixed(1)}</span> <span className="text-white/55">Bote</span></div>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Right Board - Hidden on mobile when controls tab is active */}
                <div className={`lg:flex-[1.5] glass-panel rounded-2xl md:rounded-3xl p-3 md:p-6 flex flex-col overflow-hidden min-h-0 ${mobileTab === 'controls' ? 'hidden lg:flex' : 'flex flex-1'}`}>
                    <div className="flex justify-between items-center mb-2 md:mb-4 flex-shrink-0">
                        <h3 className="text-xs md:text-base font-semibold tracking-tight">Tablero</h3>
                        <div className="text-[10px] md:text-xs text-white/50 font-mono">{drawnNumbers.length}/90</div>
                    </div>
                    {/* Grid container - aspect ratio wrapper for perfect squares */}
                    <div className="flex-1 min-h-0 flex items-center justify-center">
                        <div className="w-full max-h-full" style={{ aspectRatio: '10 / 9' }}>
                            <div className="grid grid-cols-10 grid-rows-9 gap-[2px] md:gap-1 w-full h-full">
                                {numbers.map((num) => {
                                    const isDrawn = drawnNumbers.includes(num);
                                    const isCurrent = currentNumber === num;
                                    return (
                                        <div key={num} className="relative flex items-center justify-center rounded bg-white/5">
                                            <div 
                                                className={`
                                                    flex items-center justify-center rounded-full
                                                    font-bold transition-colors duration-200
                                                    text-[10px] sm:text-xs md:text-sm lg:text-base
                                                    ${isCurrent
                                                        ? 'bg-[#ef4444] text-white shadow-lg'
                                                        : isDrawn
                                                            ? 'bg-white text-black'
                                                            : 'text-white/50'}
                                                `}
                                                style={{ height: '75%', aspectRatio: '1 / 1' }}
                                            >
                                                {num}
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                    <div className="mt-2 pt-2 border-t border-white/5 flex-shrink-0">
                        <div className="h-1.5 w-full bg-white/10 rounded-full overflow-hidden"><div className="h-full bg-gradient-to-r from-[#8b5cf6] via-[#ec4899] to-[#22d3ee] transition-all duration-500" style={{ width: `${(drawnNumbers.length / 90) * 100}%` }}></div></div>
                    </div>
                </div>
            </main>

            {/* F2 — Modal de reclamación entrante (anfitrión verifica) */}
            {isFiestaMode && fiestaClaim && !fiestaWinner && (
                <div className="absolute inset-0 z-[60] flex items-center justify-center p-4" style={{ background: 'rgba(5,6,9,0.82)', backdropFilter: 'blur(8px)' }}>
                    <div role="alertdialog" aria-modal="true" aria-labelledby="claim-title" className="w-full max-w-md rounded-[22px] p-5 md:p-6 animate-enter" style={{ background: '#13141b', border: '1px solid rgba(255,255,255,0.08)', boxShadow: '0 18px 50px rgba(0,0,0,0.45)' }}>
                        <div className="flex items-center justify-between mb-4">
                            <span className="text-[10px] font-bold uppercase tracking-widest px-2.5 py-1 rounded-full" style={{ background: 'rgba(242,93,107,0.16)', color: '#ff9aa6', border: '1px solid rgba(242,93,107,0.4)' }}>● Partida en pausa</span>
                            <span className="text-[10px] font-semibold" style={{ color: fiestaClaim.sugerenciaValida ? '#3ed993' : '#ff9aa6' }}>
                                {fiestaClaim.sugerenciaValida ? 'Sugerencia: válida' : 'Sugerencia: revisar'}
                            </span>
                        </div>

                        <div className="flex items-center gap-3 mb-4">
                            <div className="w-11 h-11 rounded-full flex items-center justify-center font-bold text-lg flex-shrink-0" style={{ background: 'linear-gradient(135deg,#8b5cf6,#ec4899 55%,#22d3ee)', color: '#fff' }}>
                                {(fiestaClaim.apodo || '?').charAt(0).toUpperCase()}
                            </div>
                            <div className="min-w-0">
                                <p id="claim-title" className="font-bold text-white truncate">{fiestaClaim.apodo}</p>
                                <p className="text-sm font-bold" style={{ color: fiestaClaim.tipo === 'BINGO' ? '#ffce4d' : '#2fd4e3' }}>
                                    canta ¡{fiestaClaim.tipo}!
                                </p>
                            </div>
                        </div>

                        <div className="rounded-2xl p-3 mb-5 flex flex-col gap-1" style={{ background: '#0a0b0f', border: '1px solid rgba(255,255,255,0.06)' }}>
                            {(fiestaClaim.cuadricula || []).map((row, r) => (
                                <div key={r} className="grid grid-cols-9 gap-1">
                                    {row.map((num, c) => {
                                        const marked = num && claimMarked.has(num);
                                        const winRow = r === claimWinningRow;
                                        let cellStyle = { background: 'transparent', color: 'transparent' };
                                        if (num) {
                                            if (winRow && marked) {
                                                cellStyle = { background: 'linear-gradient(145deg,#ffd968,#f0b429)', color: '#3a2a00', boxShadow: '0 0 0 2px rgba(255,206,77,0.5)' };
                                            } else if (marked) {
                                                cellStyle = { background: 'linear-gradient(145deg,#3ed993,#22b878)', color: '#08160f' };
                                            } else {
                                                cellStyle = { background: '#1c1e27', color: 'rgba(255,255,255,0.6)' };
                                            }
                                        }
                                        return (
                                            <div key={c} className="aspect-square rounded-[8px] flex items-center justify-center text-[11px] md:text-xs font-bold" style={cellStyle}>
                                                {num || ''}
                                            </div>
                                        );
                                    })}
                                </div>
                            ))}
                        </div>

                        <div className="flex gap-3">
                            <button autoFocus onClick={() => resolveClaim('rechazar')} disabled={resolvingClaim} className="flex-1 rounded-[13px] py-3.5 font-bold transition-colors disabled:opacity-50" style={{ background: 'rgba(242,93,107,0.16)', color: '#ff9aa6', border: '1px solid rgba(242,93,107,0.4)' }}>
                                ✗ Rechazar
                            </button>
                            <button onClick={() => resolveClaim('aprobar')} disabled={resolvingClaim} className="flex-1 rounded-[13px] py-3.5 font-bold transition-colors disabled:opacity-50" style={{ background: 'rgba(62,217,147,0.16)', color: '#8af0c0', border: '1px solid rgba(62,217,147,0.4)' }}>
                                ✓ Aprobar y anunciar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* F2 — Overlay de ganador */}
            {isFiestaMode && fiestaWinner && (
                <div role="dialog" aria-modal="true" aria-label={`${fiestaWinner.apodo} gana la partida`} className="absolute inset-0 z-[70] flex flex-col items-center justify-center text-center p-6" style={{ background: 'rgba(5,6,9,0.92)', backdropFilter: 'blur(10px)' }}>
                    <p className="text-xs font-bold uppercase tracking-[0.3em] mb-6" style={{ color: 'rgba(255,255,255,0.5)' }}>Partida finalizada</p>
                    <div className="text-6xl mb-4">🏆</div>
                    <h1 className="text-3xl md:text-5xl font-black tracking-tight mb-2">
                        <span style={{ color: '#fff' }}>{fiestaWinner.apodo}</span> <span style={{ color: 'rgba(255,255,255,0.5)' }}>gana</span>
                    </h1>
                    <p className="text-lg font-bold mb-1" style={{ color: '#ffce4d' }}>
                        BINGO{fiestaWinner.premio > 0 ? ` · ${Number(fiestaWinner.premio).toFixed(0)} puntos` : ''}
                    </p>
                    <button autoFocus onClick={nuevaPartidaTrasGanador} className="mt-8 rounded-[14px] px-8 py-3.5 font-bold" style={{ background: 'linear-gradient(135deg,#8b5cf6,#ec4899 55%,#22d3ee)', color: '#fff' }}>
                        Nueva partida
                    </button>
                </div>
            )}

            {/* F2 — Toast efímero del anfitrión */}
            {isFiestaMode && hostToast && (
                <div className="absolute bottom-4 left-1/2 -translate-x-1/2 z-[80] px-5 py-3 rounded-[14px] font-semibold text-sm animate-slide-up" style={{
                    background: hostToast.kind === 'success' ? 'rgba(62,217,147,0.16)' : 'rgba(242,93,107,0.16)',
                    color: hostToast.kind === 'success' ? '#8af0c0' : '#ff9aa6',
                    border: `1px solid ${hostToast.kind === 'success' ? 'rgba(62,217,147,0.4)' : 'rgba(242,93,107,0.4)'}`,
                }}>
                    {hostToast.msg}
                </div>
            )}
        </div>
    );
};

export default App;
