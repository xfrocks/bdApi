<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\ConversationMaster;
use XF\Mvc\ParameterBag;
use Xfrocks\Api\Util\PageNav;

class Conversation extends AbstractController
{
    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        $this->assertApiScope('conversate');
        $this->assertRegistrationRequired();
    }

    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->conversation_id) {
            return $this->actionSingle($params->conversation_id);
        }

        $params = $this
            ->params()
            ->definePageNav();

        $visitor = \XF::visitor();
        /** @var \XF\Repository\Conversation $conversionRepo */
        $conversionRepo = $this->repository('XF:Conversation');

        $finder = $conversionRepo->findConversationsStartedByUser($visitor);
        $params->limitFinderByPage($finder);

        $total = $finder->total();
        $conversations = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'conversations' => $conversations,
            'conversations_total' => $total
        ];

        PageNav::addLinksToData($data, $params, $total, 'conversations');

        return $this->api($data);
    }

    public function actionSingle($conversationId)
    {
        $conversation = $this->assertViewableConversation($conversationId);

        $data = [
            'conversation' => $this->transformEntityLazily($conversation)
        ];

        return $this->api($data);
    }

    public function actionPostIndex()
    {
        $params = $this
            ->params()
            ->define('conversation_title', 'str', 'title of the new conversation')
            ->define('recipients', 'str', 'usernames of recipients of the new conversation')
            ->define('message_body', 'str', 'content of the new conversation')
            ->defineAttachmentHash();

        $visitor = \XF::visitor();

        /** @var \XF\Service\Conversation\Creator $creator */
        $creator = $this->service('XF:Conversation\Creator', $visitor);
        $creator->setRecipients($params['recipients']);
        $creator->setContent($params['conversation_title'], $params['message_body']);

        $contentData = ['message_id' => 0];
        /** @var \Xfrocks\Api\ControllerPlugin\Attachment $attachmentPlugin */
        $attachmentPlugin = $this->plugin('Xfrocks\Api:Attachment');
        $tempHash = $attachmentPlugin->getAttachmentTempHash($contentData);

        if ($creator->getConversation()->canUploadAndManageAttachments()) {
            $creator->setAttachmentHash($tempHash);
        }

        $creator->checkForSpam();

        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $conversation = $creator->save();
        return $this->actionSingle($conversation->conversation_id);
    }

    public function actionDeleteIndex(ParameterBag $params)
    {
        $conversation = $this->assertViewableConversation($params->conversation_id);

        $recipient = $conversation->Recipients[\XF::visitor()->user_id];
        $recipient->recipient_state = 'deleted';
        $recipient->save();
        
        return $this->message(\XF::phrase('changes_saved'));
    }

    public function actionPostAttachments()
    {
        $this->params()
            ->defineFile('file')
            ->defineAttachmentHash();

        $contentData = ['message_id' => 0];
        /** @var \Xfrocks\Api\ControllerPlugin\Attachment $attachmentPlugin */
        $attachmentPlugin = $this->plugin('Xfrocks\Api:Attachment');
        $tempHash = $attachmentPlugin->getAttachmentTempHash($contentData);

        return $attachmentPlugin->doUpload($tempHash, 'conversation_message', $contentData);
    }

    protected function assertViewableConversation($conversationId, array $extraWith = [])
    {
        /** @var ConversationMaster $conversation */
        $conversation = $this->assertRecordExists('XF:ConversationMaster', $conversationId, $extraWith);
        if (!$conversation->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $conversation;
    }
}
