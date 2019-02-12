<?php

namespace Xfrocks\Api\Controller;

use XF\Entity\ConversationMaster;
use XF\Entity\LikedContent;
use XF\Mvc\ParameterBag;
use XF\Service\Conversation\MessageEditor;
use XF\Service\Conversation\Replier;
use Xfrocks\Api\Data\Params;
use Xfrocks\Api\Util\PageNav;

class ConversationMessage extends AbstractController
{
    public function preDispatch($action, ParameterBag $params)
    {
        parent::preDispatch($action, $params);

        $this->assertApiScope('conversate');
        $this->assertRegistrationRequired();
    }

    /**
     * @param ParameterBag $params
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetIndex(ParameterBag $params)
    {
        if ($params->message_id) {
            return $this->actionSingle($params->message_id);
        }

        $orderChoices = [
            'natural' => ['message_date', 'ASC'],
            'natural_reverse' => ['message_date', 'DESC']
        ];

        $params = $this
            ->params()
            ->define('conversation_id', 'uint', 'conversation id to filter')
            ->define('before', 'uint', 'date to get older messages')
            ->define('after', 'uint', 'date to get newer messages')
            ->definePageNav()
            ->defineOrder($orderChoices);

        $this->assertRegistrationRequired();

        /** @var ConversationMaster $conversation */
        $conversation = $this->assertRecordExists('XF:ConversationMaster', $params['conversation_id']);
        if (!$conversation->canView($error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\Repository\ConversationMessage $convoMessageRepo */
        $convoMessageRepo = $this->repository('XF:ConversationMessage');

        $finder = $convoMessageRepo->findMessagesForConversationView($conversation);
        $this->applyMessagesFilters($finder, $params);

        $total = $finder->total();
        $messages = $total > 0 ? $this->transformFinderLazily($finder) : [];

        $data = [
            'messages' => $messages,
            'messages_total' => $total
        ];

        $this->transformEntityIfNeeded($data, 'conversation', $conversation);
        PageNav::addLinksToData($data, $params, $total, 'conversation-messages');

        return $this->api($data);
    }

    /**
     * @param int $messageId
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionSingle($messageId)
    {
        $message = $this->assertViewableMessage($messageId);

        $data = [
            'message' => $this->transformEntityLazily($message)
        ];

        return $this->api($data);
    }

    /**
     * @return \XF\Mvc\Reply\Error|\Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostIndex()
    {
        $params = $this
            ->params()
            ->define('conversation_id', 'uint', 'id of the target conversation')
            ->define('message_body', 'str', 'content of the new message')
            ->defineAttachmentHash();

        $conversation = $this->assertViewableConversation($params['conversation_id']);
        if (!$conversation->canReply()) {
            return $this->noPermission();
        }

        /** @var Replier $replier */
        $replier = $this->service('XF:Conversation\Replier', $conversation, \XF::visitor());

        $replier->setMessageContent($params['message_body']);

        if ($conversation->canUploadAndManageAttachments()) {
            $context = [
                'conversation_id' => $params['conversation_id']
            ];

            /** @var \Xfrocks\Api\ControllerPlugin\Attachment $attachmentPlugin */
            $attachmentPlugin = $this->plugin('Xfrocks\Api:Attachment');
            $tempHash = $attachmentPlugin->getAttachmentTempHash($context);

            $replier->setAttachmentHash($tempHash);
        }

        $replier->checkForSpam();

        if (!$replier->validate($errors)) {
            return $this->error($errors);
        }

        $this->assertNotFlooding('conversation');

        $message = $replier->save();
        return $this->actionSingle($message->message_id);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPutIndex(ParameterBag $params)
    {
        $message = $this->assertViewableMessage($params->message_id);

        $params = $this
            ->params()
            ->define('message_body', 'str', 'new content of the message')
            ->defineAttachmentHash();

        $error = null;
        if (!$message->canEdit($error)) {
            return $this->noPermission($error);
        }

        /** @var MessageEditor $editor */
        $editor = $this->service('XF:Conversation\MessageEditor', $message);
        $editor->setMessageContent($params['message_body']);

        if ($message->Conversation->canUploadAndManageAttachments()) {
            $context = [
                'message_id' => $message->message_id
            ];

            /** @var \Xfrocks\Api\ControllerPlugin\Attachment $attachmentPlugin */
            $attachmentPlugin = $this->plugin('Xfrocks\Api:Attachment');
            $tempHash = $attachmentPlugin->getAttachmentTempHash($context);

            $editor->setAttachmentHash($tempHash);
        }

        $editor->checkForSpam();

        if (!$editor->validate($errors)) {
            return $this->error($errors);
        }

        $message = $editor->save();
        return $this->actionSingle($message->message_id);
    }

    public function actionDeleteIndex(ParameterBag $params)
    {
        return $this->noPermission();
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Reroute|\Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetAttachments(ParameterBag $params)
    {
        $message = $this->assertViewableMessage($params->message_id);

        $params = $this
            ->params()
            ->define('attachment_id', 'uint');

        if ($params['attachment_id'] > 0) {
            return $this->rerouteController('Xfrocks\Api\Controller\Attachment', 'get-data');
        }

        $finder = $message->getRelationFinder('Attachments');

        $data = [
            'attachments' => $message->attach_count > 0 ? $this->transformFinderLazily($finder) : []
        ];

        return $this->api($data);
    }

    /**
     * @return \XF\Mvc\Reply\Error|\Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionPostAttachments()
    {
        $params = $this
            ->params()
            ->defineFile('file')
            ->define('conversation_id', 'uint', 'id of the container conversation of the target message')
            ->define('message_id', 'uint', 'id of the target message')
            ->defineAttachmentHash();

        if (empty($params['conversation_id']) && empty($params['message_id'])) {
            return $this->error(\XF::phrase('bdapi_slash_conversation_messages_attachments_requires_ids'), 400);
        }

        $context = [
            'conversation_id' => $params['conversation_id'],
            'message_id' => $params['message_id']
        ];

        /** @var \Xfrocks\Api\ControllerPlugin\Attachment $attachmentPlugin */
        $attachmentPlugin = $this->plugin('Xfrocks\Api:Attachment');
        $tempHash = $attachmentPlugin->getAttachmentTempHash($context);

        return $attachmentPlugin->doUpload($tempHash, 'conversation_message', $context);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostReport(ParameterBag $params)
    {
        $message = $this->assertViewableMessage($params->message_id);

        $params = $this
            ->params()
            ->define('message', 'str', 'reason of the report');

        $error = null;
        if (!$message->canReport($error)) {
            return $this->noPermission($error);
        }

        /** @var \XF\Service\Report\Creator $creator */
        $creator = $this->service('XF:Report\Creator', 'conversation_message', $message);
        $creator->setMessage($params['message']);

        if (!$creator->validate($errors)) {
            return $this->error($errors);
        }

        $creator->save();

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $params
     * @return \Xfrocks\Api\Mvc\Reply\Api
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionGetLikes(ParameterBag $params)
    {
        $message = $this->assertViewableMessage($params->message_id);

        $finder = $message->getRelationFinder('Likes');
        $finder->with('Liker');

        $users = [];

        /** @var LikedContent $liked */
        foreach ($finder->fetch() as $liked) {
            $users[] = [
                'user_id' => $liked->Liker->user_id,
                'username' => $liked->Liker->username
            ];
        }

        $data = ['users' => $users];
        return $this->api($data);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionPostLikes(ParameterBag $params)
    {
        $message = $this->assertViewableMessage($params->message_id);

        $error = null;
        if (!$message->canLike($error)) {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        if (empty($message->Likes[$visitor->user_id])) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $likeRepo->toggleLike('conversation_message', $message->message_id, $visitor);
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Message
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionDeleteLikes(ParameterBag $params)
    {
        $message = $this->assertViewableMessage($params->message_id);

        $error = null;
        if (!$message->canLike($error)) {
            return $this->noPermission($error);
        }

        $visitor = \XF::visitor();
        if (!empty($message->Likes[$visitor->user_id])) {
            /** @var \XF\Repository\LikedContent $likeRepo */
            $likeRepo = $this->repository('XF:LikedContent');
            $likeRepo->toggleLike('conversation_message', $message->message_id, $visitor);
        }

        return $this->message(\XF::phrase('changes_saved'));
    }

    /**
     * @param int $messageId
     * @param array $extraWith
     * @return \XF\Entity\ConversationMessage
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableMessage($messageId, array $extraWith = [])
    {
        /** @var \XF\Entity\ConversationMessage $message */
        $message = $this->assertRecordExists('XF:ConversationMessage', $messageId, $extraWith);
        if (!$message->canView($error)) {
            throw $this->exception($this->noPermission($error));
        }

        return $message;
    }

    /**
     * @param int $conversationId
     * @param array $extraWith
     * @return ConversationMaster
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertViewableConversation($conversationId, array $extraWith = [])
    {
        $visitor = \XF::visitor();

        /** @var \XF\Finder\ConversationUser $finder */
        $finder = $this->finder('XF:ConversationUser');
        $finder->forUser($visitor, false);
        $finder->where('conversation_id', $conversationId);
        $finder->with($extraWith);

        /** @var \XF\Entity\ConversationUser|null $conversation */
        $conversation = $finder->fetchOne();
        /** @var ConversationMaster|null $convoMaster */
        $convoMaster = $conversation ? $conversation->Master : null;
        if (!$conversation || !$convoMaster) {
            throw $this->exception($this->notFound(\XF::phrase('requested_conversation_not_found')));
        }

        return $conversation->Master;
    }

    /**
     * @param \XF\Finder\ConversationMessage $finder
     * @param Params $params
     */
    protected function applyMessagesFilters($finder, Params $params)
    {
        if ($params['order'] === 'natural_reverse') {
            $finder->resetOrder()
                   ->order('message_date', 'DESC');
        }

        if ($params['before'] > 0) {
            $finder->where('message_date', '<', $params['before']);
        }

        if ($params['after'] > 0) {
            $finder->where('message_date', '>', $params['after']);
        }

        $params->limitFinderByPage($finder);
    }
}
