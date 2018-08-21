<?php

namespace Xfrocks\Api\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use XF\Util\Random;

class Test extends Command
{
    const VERSION_ID = 2018082101;

    public $prefix = 'api-test';
    public $users = 3;

    public function createForum(array &$data)
    {
        if (!isset($data['forum'])) {
            $app = \XF::app();
            /** @var \XF\Entity\Node $node */
            $node = $app->em()->create('XF:Node');
            $node->display_in_list = false;
            $node->node_type_id = 'Forum';
            $node->title = sprintf('%s-%d', $this->prefix, \XF::$time);

            /** @var \XF\Entity\Forum $forum */
            $forum = $node->getDataRelationOrDefault();
            $node->addCascadedSave($forum);

            $node->save();

            $data['forum'] = [
                'node_id' => $node->node_id,
                'version_id' => self::VERSION_ID
            ];
        }

        return $data['forum'];
    }

    public function createUsers(array &$data)
    {
        $app = \XF::app();
        if (!isset($data['users'])) {
            $data['users'] = [];
        }

        for ($i = 0; $i < $this->users; $i++) {
            if (isset($data['users'][$i])) {
                continue;
            }

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

            $data['users'][$i] = [
                'user_id' => $user->user_id,
                'username' => $username,
                'password' => $password,
                'version_id' => self::VERSION_ID
            ];
        }

        return $data['users'];
    }

    public function createApiClient(array $userData, array &$data)
    {
        if (!isset($data['apiClient'])) {
            $app = \XF::app();
            /** @var \Xfrocks\Api\Repository\Client $clientRepo */
            $clientRepo = $app->repository('Xfrocks\Api:Client');
            $client = $clientRepo->newClient([
                'client_id' => $clientRepo->generateClientId(),
                'client_secret' => $clientRepo->generateClientSecret(),
                'user_id' => $userData['user_id']
            ]);

            $client->name = $this->prefix;
            $client->description = __METHOD__;
            $client->redirect_uri = $app->options()->boardUrl;
            $client->save();

            $data['apiClient'] = [
                'client_id' => $client->client_id,
                'client_secret' => $client->client_secret,
                'version_id' => self::VERSION_ID
            ];
        }

        return $data['apiClient'];
    }

    protected function configure()
    {
        $this
            ->setName('xfrocks-api:test')
            ->setDescription('Run API tests');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!\XF::$debugMode) {
            throw new \LogicException('XenForo must be in debug mode for testing');
        }

        $jsonPath = "/tmp/{$this->prefix}.json";

        $data = [];
        if (file_exists($jsonPath)) {
            $json = file_get_contents($jsonPath);
            if (is_string($json)) {
                $data = @json_decode($json, true) ?: [];
            }
        }
        $dataBefore = md5(serialize($data));

        $this->createForum($data);
        $this->createUsers($data);

        $this->createApiClient($data['users'][0], $data);

        $dataAfter = md5(serialize($data));

        if ($dataAfter === $dataBefore) {
            $dir = dirname(dirname(__DIR__));
            chdir($dir);
            passthru('composer test', $returnVar);
        } else {
            file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT));
            $output->writeln("Written test data to {$jsonPath}, execute again to run tests");
            $returnVar = 0;
        }

        return $returnVar;
    }
}
