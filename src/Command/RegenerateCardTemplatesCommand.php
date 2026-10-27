<?php

namespace App\Command;

use App\Service\SpanishBingoCardGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rehace los cartones libres del pool con el generador actual. Los que está
 * usando una partida no se tocan: basta con volver a lanzarlo más tarde para
 * que entren cuando se liberen. Las partidas ya creadas no cambian, porque
 * cada cartón copia su cuadrícula al repartirse.
 */
#[AsCommand(
    name: 'app:pool:regenerate-cards',
    description: 'Regenera los cartones libres del pool con el formato de bingo español',
)]
class RegenerateCardTemplatesCommand extends Command
{
    private const BATCH = 200;

    public function __construct(
        private EntityManagerInterface $em,
        private SpanishBingoCardGenerator $cardGenerator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conn = $this->em->getConnection();

        $seen = [];
        foreach ($conn->fetchFirstColumn('SELECT flat_numbers FROM card_template') as $flat) {
            $seen[implode(',', json_decode($flat, true))] = true;
        }

        $total = 0;
        $lastId = null;
        do {
            $updated = $conn->transactional(function () use ($conn, &$seen, &$lastId): int {
                // Los que coja una partida mientras tanto se saltan.
                $ids = $conn->fetchFirstColumn(
                    'SELECT id FROM card_template WHERE is_available = TRUE'
                    . ($lastId !== null ? ' AND id > :last' : '')
                    . ' ORDER BY id LIMIT ' . self::BATCH . ' FOR UPDATE SKIP LOCKED',
                    $lastId !== null ? ['last' => $lastId] : []
                );

                foreach ($ids as $id) {
                    do {
                        $card = $this->cardGenerator->generateCard();
                        $key = implode(',', $card['flatNumbers']);
                    } while (isset($seen[$key]));
                    $seen[$key] = true;

                    $conn->executeStatement(
                        'UPDATE card_template SET grid = :grid, flat_numbers = :flat WHERE id = :id',
                        ['grid' => json_encode($card['grid']), 'flat' => json_encode($card['flatNumbers']), 'id' => $id]
                    );
                    $lastId = $id;
                }

                return count($ids);
            });
            $total += $updated;
        } while ($updated === self::BATCH);

        $io->success(sprintf('%d cartones regenerados.', $total));

        return Command::SUCCESS;
    }
}
