<?php

namespace App\Command;

use App\Service\RoomReleaser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Devuelve al pool las salas abandonadas. Pensado para una tarea programada
 * (Coolify, cada hora): sin ella, una sala que el anfitrión no libera se queda
 * ocupada hasta que alguien pulsa el botón del panel.
 */
#[AsCommand(
    name: 'app:rooms:release-stale',
    description: 'Release rooms with no activity for the given number of hours',
)]
class ReleaseStaleRoomsCommand extends Command
{
    public function __construct(
        private RoomReleaser $roomReleaser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Hours without activity', 12);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $hours = (int) $input->getOption('hours');
        if ($hours < 1) {
            $io->error('--hours must be at least 1');

            return Command::INVALID;
        }

        $released = $this->roomReleaser->releaseStale(new \DateTimeImmutable("-{$hours} hours"));
        $io->success(sprintf('%d room(s) released (inactive for more than %d hours).', $released, $hours));

        return Command::SUCCESS;
    }
}
