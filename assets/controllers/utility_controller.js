import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        copy: String,
        confirm: String,
    };

    static targets = ['source'];

    copy(event) {
        event.preventDefault();
        const text = this.copyValue || (this.hasSourceTarget ? this.sourceTarget.innerText : '');
        if (!text) {
            return;
        }

        navigator.clipboard?.writeText(text)
            .then(() => this.showToast('Copiado', 'El contenido está en el portapapeles'))
            .catch(() => this.showToast('Error', 'No se pudo copiar', true));
    }

    confirm(event) {
        if (!window.confirm(this.confirmValue || '¿Seguro?')) {
            event.preventDefault();
        }
    }

    showToast(title, message, danger = false) {
        let toast = document.getElementById('utility-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'utility-toast';
            toast.className = 'fixed bottom-4 right-4 rounded-lg border shadow-lg px-4 py-3 text-sm';
            document.body.appendChild(toast);
        }
        toast.classList.toggle('bg-emerald-600', !danger);
        toast.classList.toggle('border-emerald-700', !danger);
        toast.classList.toggle('text-white', !danger);
        toast.classList.toggle('bg-red-600', danger);
        toast.classList.toggle('border-red-700', danger);
        toast.textContent = `${title}: ${message}`;
        toast.style.opacity = '1';
        setTimeout(() => toast && (toast.style.opacity = '0'), 2200);
    }
}
