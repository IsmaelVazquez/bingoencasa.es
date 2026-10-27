<?php

/*
 * Proceso hijo de ConcurrencyTest. Arranca el kernel de test, avisa con READY
 * y espera a que aparezca el fichero barrera para que todos los hijos ataquen
 * a la vez. Imprime el resultado en la última línea.
 *
 * php worker.php <barrera> acquire-room
 * php worker.php <barrera> take-cards <n>
 * php worker.php <barrera> json <sesion> <metodo> <uri> <cuerpo-json>
 * php worker.php <barrera> form <sesion> <uri> <campos-json>
 */

use App\Kernel;
use App\Repository\CardTemplateRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
(new Dotenv())->bootEnv(dirname(__DIR__, 2) . '/.env');

$kernel = new Kernel('test', false);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$em = $container->get(EntityManagerInterface::class);
$em->getConnection()->executeQuery('SELECT 1');

[, $barrier, $action] = $argv;
$args = array_slice($argv, 3);

$client = null;
if ($action === 'json' || $action === 'form') {
    $client = $container->get('test.client');
    if ($args[0] !== '') {
        $client->getCookieJar()->set(new Cookie('MOCKSESSID', $args[0]));
    }
}

echo "READY\n";
while (!file_exists($barrier)) {
    usleep(500);
}

switch ($action) {
    case 'acquire-room':
        $room = $container->get(RoomRepository::class)->acquireAvailableRoom();
        $em->flush();
        echo $room?->getId()?->toBase32() ?? '', "\n";
        break;

    case 'take-cards':
        $ids = $em->wrapInTransaction(fn () => array_map(
            fn ($t) => $t->getId(),
            $container->get(CardTemplateRepository::class)->getAvailableCards((int) $args[0]),
        ));
        echo implode(',', $ids), "\n";
        break;

    case 'json':
        $client->request($args[1], $args[2], server: ['CONTENT_TYPE' => 'application/json'], content: $args[3]);
        echo $client->getResponse()->getStatusCode(), "\n";
        break;

    case 'form':
        $client->request('POST', $args[1], json_decode($args[2], true));
        echo $client->getResponse()->getStatusCode(), "\n";
        break;
}
