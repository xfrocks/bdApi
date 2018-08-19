<?php

namespace Xfrocks\Api\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Util\Random;

class PreTest extends Command
{
    public $prefix = 'api-test';
    public $users = 3;

    public function createUsers(array &$data = [])
    {
        $app = \XF::app();
        $data['users'] = [];

        for ($i = 0; $i < $this->users; $i++) {
            $username = sprintf('%s-%d-%d', $this->prefix, \XF::$time, $i + 1);
            $password = Random::getRandomString(32);

            /** @var \XF\Service\User\Registration $registration */
            $registration = $app->service('XF:User\Registration');
            $user = $registration->getUser();
            $user->setOption('admin_edit', true);
            $registration->setFromInput(['username' => $username]);
            $registration->setPassword($password, '', false);
            $registration->skipEmailConfirmation();
            $registration->save();

            $data['users'][] = [
                'user_id' => $user->user_id,
                'username' => $username,
                'password' => $password
            ];
        }

        return $data['users'];
    }

    public function createApiClient(array &$data = [])
    {
        $app = \XF::app();
        /** @var \Xfrocks\Api\Repository\Client $clientRepo */
        $clientRepo = $app->repository('Xfrocks\Api:Client');
        $client = $clientRepo->newClient([
            'client_id' => $clientRepo->generateClientId(),
            'client_secret' => $clientRepo->generateClientSecret(),
            'user_id' => $data['users'][0]['user_id']
        ]);

        $client->name = $this->prefix;
        $client->description = __METHOD__;
        $client->redirect_uri = $app->options()->boardUrl;
        $client->save();

        $data['apiClient'] = [
            'client_id' => $client->client_id,
            'client_secret' => $client->client_secret
        ];

        return $data['apiClient'];
    }

    protected function configure()
    {
        $this
            ->setName('xfrocks-api:pre-test')
            ->setDescription('Prepare environment for API testings');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!\XF::$debugMode) {
            throw new \LogicException('XenForo must be in debug mode for testing');
        }

        $data = [];
        $this->createUsers($data);
        $this->createApiClient($data);

        $jsonPath = "/tmp/{$this->prefix}.json";
        file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT));

        $output->writeln("Written test data to {$jsonPath}");

        return 0;
    }
}
