<?php

class bdApi_Model_ClientContent extends XenForo_Model
{
    const FETCH_CLIENT = 0x01;
    const FETCH_USER = 0x02;

    public function getList(array $conditions = array(), array $fetchOptions = array())
    {
        $clientContents = $this->getClientContents($conditions, $fetchOptions);
        $list = array();

        foreach ($clientContents as $id => $clientContent) {
            $list[$id] = $clientContent['client_id'];
        }

        return $list;
    }

    public function getClientContentById($id, array $fetchOptions = array())
    {
        $clientContents = $this->getClientContents(array('client_content_id' => $id), $fetchOptions);

        return reset($clientContents);
    }

    public function getClientContentIdsInRange($start, $limit)
    {
        $db = $this->_getDb();

        return $db->fetchCol($db->limit('
            SELECT client_content_id
            FROM xf_bdapi_client_content
            WHERE client_content_id > ?
            ORDER BY client_content_id
        ', $limit), $start);
    }

    public function getClientContents(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareClientContentConditions($conditions, $fetchOptions);

        $orderClause = $this->prepareClientContentOrderOptions($fetchOptions);
        $joinOptions = $this->prepareClientContentFetchOptions($fetchOptions);
        $limitOptions = $this->prepareLimitFetchOptions($fetchOptions);

        $clientContents = $this->fetchAllKeyed($this->limitQueryResults("
            SELECT client_content.*
                $joinOptions[selectFields]
            FROM `xf_bdapi_client_content` AS client_content
                $joinOptions[joinTables]
            WHERE $whereConditions
                $orderClause
            ", $limitOptions['limit'], $limitOptions['offset']
        ), 'client_content_id');

        foreach ($clientContents as &$clientContentRef) {
            $clientContentRef['extraData'] = unserialize($clientContentRef['extra_data']);
        }

        return $clientContents;
    }

    public function countClientContents(array $conditions = array(), array $fetchOptions = array())
    {
        $whereConditions = $this->prepareClientContentConditions($conditions, $fetchOptions);
        $joinOptions = $this->prepareClientContentFetchOptions($fetchOptions);

        return $this->_getDb()->fetchOne("
            SELECT COUNT(*)
            FROM `xf_bdapi_client_content` AS client_content
                $joinOptions[joinTables]
            WHERE $whereConditions
        ");
    }

    public function prepareClientContentConditions(array $conditions = array(), array $fetchOptions = array())
    {
        $sqlConditions = array();
        $db = $this->_getDb();

        if (isset($conditions['client_content_id'])) {
            if (is_array($conditions['client_content_id'])) {
                if (!empty($conditions['client_content_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "client_content.client_content_id IN (" . $db->quote($conditions['client_content_id']) . ")";
                }
            } else {
                $sqlConditions[] = "client_content.client_content_id = " . $db->quote($conditions['client_content_id']);
            }
        }

        if (isset($conditions['client_id'])) {
            if (is_array($conditions['client_id'])) {
                if (!empty($conditions['client_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "client_content.client_id IN (" . $db->quote($conditions['client_id']) . ")";
                }
            } else {
                $sqlConditions[] = "client_content.client_id = " . $db->quote($conditions['client_id']);
            }
        }

        if (isset($conditions['content_type'])) {
            if (is_array($conditions['content_type'])) {
                if (!empty($conditions['content_type'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "client_content.content_type IN (" . $db->quote($conditions['content_type']) . ")";
                }
            } else {
                $sqlConditions[] = "client_content.content_type = " . $db->quote($conditions['content_type']);
            }
        }

        if (isset($conditions['content_id'])) {
            if (is_array($conditions['content_id'])) {
                if (!empty($conditions['content_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "client_content.content_id IN (" . $db->quote($conditions['content_id']) . ")";
                }
            } else {
                $sqlConditions[] = "client_content.content_id = " . $db->quote($conditions['content_id']);
            }
        }

        if (isset($conditions['title'])) {
            if (is_array($conditions['title'])) {
                if (!empty($conditions['title'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "client_content.title IN (" . $db->quote($conditions['title']) . ")";
                }
            } else {
                $sqlConditions[] = "client_content.title = " . $db->quote($conditions['title']);
            }
        }

        if (isset($conditions['date'])) {
            if (is_array($conditions['date'])) {
                if (!empty($conditions['date'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "client_content.date IN (" . $db->quote($conditions['date']) . ")";
                }
            } else {
                $sqlConditions[] = "client_content.date = " . $db->quote($conditions['date']);
            }
        }

        if (isset($conditions['user_id'])) {
            if (is_array($conditions['user_id'])) {
                if (!empty($conditions['user_id'])) {
                    // only use IN condition if the array is not empty (nasty!)
                    $sqlConditions[] = "client_content.user_id IN (" . $db->quote($conditions['user_id']) . ")";
                }
            } else {
                $sqlConditions[] = "client_content.user_id = " . $db->quote($conditions['user_id']);
            }
        }

        if (!empty($conditions['filter'])) {
            if (is_array($conditions['filter'])) {
                $filterQuoted = XenForo_Db::quoteLike($conditions['filter'][0], $conditions['filter'][1], $db);
            } else {
                $filterQuoted = XenForo_Db::quoteLike($conditions['filter'], 'lr', $db);
            }

            $sqlConditions[] = sprintf('client_content.title LIKE %1$s', $filterQuoted);
        }

        return $this->getConditionsForClause($sqlConditions);
    }

    public function prepareClientContentFetchOptions(array $fetchOptions = array())
    {
        $selectFields = '';
        $joinTables = '';

        if (!empty($fetchOptions['join'])) {
            if ($fetchOptions['join'] & self::FETCH_CLIENT) {
                $selectFields .= ',
                    client.*';
                $joinTables .= '
                    INNER JOIN `xf_bdapi_client` AS client
                    ON (client.client_id = client_content.client_id)';
            }

            if ($fetchOptions['join'] & self::FETCH_USER) {
                $selectFields .= ',
                    user.*';
                $joinTables .= '
                    LEFT JOIN `xf_user` AS user
                    ON (user.user_id = client_content.user_id)';
            }
        }

        return array(
            'selectFields' => $selectFields,
            'joinTables' => $joinTables
        );
    }

    public function prepareClientContentOrderOptions(array $fetchOptions = array(), $defaultOrderSql = '')
    {
        $choices = array(
            'client_content_id' => 'client_content.client_content_id',
            'date' => 'client_content.date',
        );

        return $this->getOrderByClause($choices, $fetchOptions, $defaultOrderSql);
    }

}