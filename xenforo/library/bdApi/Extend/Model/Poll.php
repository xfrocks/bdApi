<?php

class bdApi_Extend_Model_Poll extends XFCP_bdApi_Extend_Model_Poll
{
    public function prepareApiDataForPoll(array $poll, $canVote, $selfLink)
    {
        $poll = $this->preparePoll($poll, $canVote);

        $publicKeys = array(
            // xf_poll
            'poll_id' => 'poll_id',
            'question' => 'poll_question',
            'voter_count' => 'poll_vote_count',
            'max_votes' => 'poll_max_votes',

            // XenForo_Model_Poll::preparePoll
            'open' => 'poll_is_open',
            'hasVoted' => 'poll_is_voted',
        );

        $data = bdApi_Data_Helper_Core::filter($poll, $publicKeys);

        $data['responses'] = array();
        foreach ($poll['responses'] as $responseId => $response) {
            $responseData = array(
                'response_id' => $responseId,
                'response_answer' => $response['response'],
            );

            if (!empty($data['poll_is_voted'])) {
                $responseData['response_is_voted'] = $response['hasVoted'];
            }

            $data['responses'][] = $responseData;
        }

        $data['permissions'] = array(
            'vote' => $poll['canVote'],
            'result' => $poll['canViewResults'],
        );

        $data['links'] = array(
            'vote' => $selfLink . '/votes',
            'results' => $selfLink . '/results',
        );

        return $data;
    }

    public function bdApi_actionPostVotes(array $poll, bdApi_ControllerApi_Abstract $controller)
    {
        if (!$this->canVoteOnPoll($poll, $errorPhraseKey)) {
            throw $controller->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $responseIds = $controller->getInput()->filterSingle(
            'response_ids',
            XenForo_Input::UINT,
            array('array' => true)
        );
        $responseId = $controller->getInput()->filterSingle('response_id', XenForo_Input::UINT);
        if ($responseId > 0) {
            $responseIds[] = $responseId;
            $responseIds = array_unique($responseIds);
        }

        if (empty($responseIds)) {
            if (!$responseIds) {
                return $controller->responseError(new XenForo_Phrase('bdapi_slash_poll_vote_requires_response_id'));
            }
        }

        if ($poll['max_votes'] > 0
            && count($responseIds) > $poll['max_votes']
        ) {
            return $controller->responseError(new XenForo_Phrase(
                'you_may_select_up_to_x_choices',
                array('max' => $poll['max_votes'])
            ));
        }

        if ($this->voteOnPoll($poll['poll_id'], $responseIds)) {
            return $controller->responseMessage(new XenForo_Phrase('changes_saved'));
        } else {
            return $controller->responseError(new XenForo_Phrase('unexpected_error_occurred'));
        }
    }

    public function bdApi_actionGetResults(array $poll, $canVote, $selfLink, bdApi_ControllerApi_Abstract $controller)
    {
        $poll = $this->preparePoll($poll, $canVote);
        $pollData = $this->prepareApiDataForPoll($poll, $canVote, $selfLink);

        $results = array();
        foreach ($pollData['responses'] as $responseData) {
            $response = $poll['responses'][$responseData['response_id']];

            $resultData = $responseData;
            $resultData['response_vote_count'] = $response['response_vote_count'];

            if (!empty($poll['public_votes'])) {
                $resultData['voters'] = array();
                if (!empty($response['voters'])) {
                    $resultData['voters'] = array_values($response['voters']);
                }
            }

            $results[] = $resultData;
        }

        $data = array(
            'results' => $controller->_filterDataMany($results),
        );

        if (!$controller->_isFieldExcluded('poll')) {
            $data['poll'] = $controller->_filterDataSingle($pollData, array('poll'));
        }

        return $controller->responseData('bdApi_ViewApi_Helper_Poll_Results', $data);
    }
}
