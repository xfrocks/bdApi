<?php

class bdApi_ViewRenderer_Xml extends XenForo_ViewRenderer_Xml
{
    public function renderError($error)
    {
        $document = new DOMDocument('1.0', 'utf-8');
        $document->formatOutput = true;

        if (!is_array($error)) {
            $error = array($error);
        }

        $errors = array();

        foreach ($error as $errorMessage) {
            $errors[] = array(
                '_key' => 'error',
                '_children' => array(array(
                    '_type' => 'cdata',
                    '_value' => $errorMessage,
                ))
            );
        }

        return self::xmlEncodeForOutput(array('errors' => $errors,));
    }

    public function renderMessage($message)
    {
        return self::xmlEncodeForOutput(array('response' => array(
            'status' => 'ok',
            'message' => $message,
        ),));
    }

    public function renderView($viewName, array $params = array(), $templateName = '', XenForo_ControllerResponse_View $subView = null)
    {
        $viewOutput = $this->renderViewObject($viewName, 'Xml', $params, $templateName);

        if (is_array($viewOutput)) {
            return self::xmlEncodeForOutput($viewOutput);
        } else
            if ($viewOutput === null) {
                return self::xmlEncodeForOutput($this->getDefaultOutputArray($viewName, $params, $templateName));
            } else {
                return $viewOutput;
            }
    }

    public function getDefaultOutputArray($viewName, $params, $templateName)
    {
        return $params;
    }

    protected static function _xmlEncodeNode(DOMDocument $document, DOMElement $parentNode, array $input)
    {
        foreach ($input as $key => $value) {
            $children = false;

            $nodeKey = $key;
            if (is_numeric($nodeKey)) {
                // numeric key is not accepted
                // so we have to process it further
                $parentNodeKey = $parentNode->tagName;
                if (substr($parentNodeKey, -1) == 's' AND preg_match('/^[a-z]+$/', $parentNodeKey)) {
                    // try to guess a good key using parent key
                    $nodeKey = substr($parentNodeKey, 0, -1);
                } else {
                    // unable to guess it, use a generic key...
                    $nodeKey = 'key_' . $key;
                }
            }

            $nodeValue = $value;

            if (is_object($value)) {
                // force the object to string
                // and use it as a value with a type of CData
                $value = array($key => array(
                    '_type' => 'cdata',
                    '_value' => '' . $value,
                ));
            }

            if (is_array($value)) {
                // this value contains many other values
                // so it should be a parent
                $children = $value;

                // a custom key can be specified with numeric-based array
                if (!empty($value['_key']))
                    $nodeKey = $value['_key'];

                // normally, children will be detected as all the elements
                // of a array. However, a specific set of children can be
                // specified using _children like this. Refer renderError()
                // to understand how to use this
                if (!empty($value['_children']))
                    $children = $value['_children'];

                // an array can still be recognized as a single value
                // if it has _value like below.
                if (!empty($value['_value'])) {
                    $children = false;
                    $nodeValue = $value['_value'];
                }
            }

            if (is_array($children)) {
                $nodeObj = $document->createElement($nodeKey);

                // recursively process children elements
                self::_xmlEncodeNode($document, $nodeObj, $children);

                $parentNode->appendChild($nodeObj);
            } else {
                $nodeType = 'value';

                // there are several type of value, it can be specified
                // using _type. Supported types:
                // cdata
                if (is_array($value) AND !empty($value['_type'])) {
                    $nodeType = strtolower($value['_type']);
                }

                switch ($nodeType) {
                    case 'cdata':
                        $parentNode->appendChild($document->createCDATASection($nodeValue));
                        break;
                    default:
                        $parentNode->appendChild($document->createElement($nodeKey, htmlentities($nodeValue)));
                        break;
                }
            }
        }
    }

    public static function xmlEncodeForOutput($input, $addDefaultParams = true)
    {
        if ($addDefaultParams) {
            self::_addDefaultParams($input);
        }

        $document = new DOMDocument('1.0', 'utf-8');
        $document->formatOutput = true;

        $rootNode = $document->createElement('xenforo');

        self::_xmlEncodeNode($document, $rootNode, $input);

        $document->appendChild($rootNode);

        return $document->saveXML();
    }

    protected static function _addDefaultParams(array &$params = array())
    {
        bdApi_Data_Helper_Core::addDefaultResponse($params);
    }

}
