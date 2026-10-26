<?php

namespace App\Command;

use App\Entity\CardTemplate;
use App\Entity\Room;
use App\Service\RoomCodeGenerator;
use App\Service\SpanishBingoCardGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:init-pool',
    description: 'Initialize the pool of rooms and card templates',
)]
class InitPoolCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoomCodeGenerator $codeGenerator,
        private SpanishBingoCardGenerator $cardGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('rooms', 'r', InputOption::VALUE_OPTIONAL, 'Number of rooms to create', 300)
            ->addOption('cards', 'c', InputOption::VALUE_OPTIONAL, 'Number of card templates to create', 2000)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force recreation (deletes existing data)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $roomCount = (int) $input->getOption('rooms');
        $cardCount = (int) $input->getOption('cards');
        $force = $input->getOption('force');

        $io->title('Initializing BingoEnCasa Pool');

        if ($force) {
            $io->warning('Force mode: deleting existing rooms and card templates...');
            $this->em->createQuery('DELETE FROM App\Entity\CardTemplate')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Card')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Claim')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Draw')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Game')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Player')->execute();
            $this->em->createQuery('DELETE FROM App\Entity\Room')->execute();
            $io->info('Existing data deleted.');
        }

        $existingRooms = $this->em->getRepository(Room::class)->count([]);
        $existingCards = $this->em->getRepository(CardTemplate::class)->count([]);

        $io->section('Creating Rooms');
        $roomsToCreate = max(0, $roomCount - $existingRooms);

        if ($roomsToCreate > 0) {
            $io->progressStart($roomsToCreate);

            $usedCodes = [];
            $existingCodes = $this->em->createQuery('SELECT r.code FROM App\Entity\Room r')
                ->getResult();
            foreach ($existingCodes as $row) {
                $usedCodes[$row['code']] = true;
            }

            for ($i = 0; $i < $roomsToCreate; $i++) {
                $room = new Room();

                do {
                    $code = $this->codeGenerator->generateUniqueCode();
                } while (isset($usedCodes[$code]));

                $usedCodes[$code] = true;
                $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

                $room->setName('Sala de Bingo');
                $room->setCode($code);
                $room->setPin($pin);
                $room->setIsAvailable(true);
                $room->setMaxPlayers(15);

                $this->em->persist($room);
                $io->progressAdvance();

                if ($i % 50 === 0) {
                    $this->em->flush();
                }
            }

            $this->em->flush();
            $io->progressFinish();
            $io->success("Created $roomsToCreate rooms");
        } else {
            $io->info("$existingRooms rooms already exist, skipping creation");
        }

        $io->section('Creating Card Templates');
        $cardsToCreate = max(0, $cardCount - $existingCards);

        if ($cardsToCreate > 0) {
            $io->progressStart($cardsToCreate);

            $existingFlatNumbers = [];
            $existingCardsData = $this->em->createQuery('SELECT c.flatNumbers FROM App\Entity\CardTemplate c')
                ->getResult();
            foreach ($existingCardsData as $row) {
                $key = implode(',', $row['flatNumbers']);
                $existingFlatNumbers[$key] = true;
            }

            $created = 0;
            $attempts = 0;
            $maxAttempts = $cardsToCreate * 10;

            while ($created < $cardsToCreate && $attempts < $maxAttempts) {
                $attempts++;
                $cardData = $this->cardGenerator->generateCard();
                $key = implode(',', $cardData['flatNumbers']);

                if (!isset($existingFlatNumbers[$key])) {
                    $existingFlatNumbers[$key] = true;

                    $cardTemplate = new CardTemplate();
                    $cardTemplate->setGrid($cardData['grid']);
                    $cardTemplate->setFlatNumbers($cardData['flatNumbers']);
                    $cardTemplate->setIsAvailable(true);

                    $this->em->persist($cardTemplate);
                    $created++;
                    $io->progressAdvance();

                    if ($created % 100 === 0) {
                        $this->em->flush();
                    }
                }
            }

            $this->em->flush();
            $io->progressFinish();

            if ($created < $cardsToCreate) {
                $io->warning("Only created $created unique cards (target was $cardsToCreate)");
            } else {
                $io->success("Created $created card templates");
            }
        } else {
            $io->info("$existingCards card templates already exist, skipping creation");
        }

        $finalRooms = $this->em->getRepository(Room::class)->count([]);
        $finalCards = $this->em->getRepository(CardTemplate::class)->count([]);
        $availableRooms = $this->em->getRepository(Room::class)->count(['isAvailable' => true]);
        $availableCards = $this->em->getRepository(CardTemplate::class)->count(['isAvailable' => true]);

        $io->section('Pool Summary');
        $io->table(
            ['Resource', 'Total', 'Available'],
            [
                ['Rooms', $finalRooms, $availableRooms],
                ['Card Templates', $finalCards, $availableCards],
            ]
        );

        $io->success('Pool initialization complete!');

        return Command::SUCCESS;
    }
}
