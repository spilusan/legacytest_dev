<?php
/**
 * Class Myshipserv_ApiServices_DomToArray
 *
 * This class will create an array from a HTML string
 */

class Myshipserv_ApiServices_HtmlToArray
{

    /**
     * Creates a hierarchical array representation of a HTML(DOM) text
     *
     * @param string $html
     * @param string $startingTagId
     * @return array
     */
    public function convertHtmlToArray($html, $startingTagId)
    {

        $domToArray = function ($node, $startClass = null, $render = false) use (&$domToArray) {
            $result = array();

            if ($node->nodeType === XML_TEXT_NODE) {
                if ($startClass === null || $render === true) {
                    $result = $node->nodeValue;
                }
            } else {
                if ($node->hasChildNodes()) {

                    foreach ($node->childNodes as $childNode) {
                        if ($childNode->nodeName === '#text') {
                            $textNode = $domToArray($childNode, $startClass, $render);
                            if (strlen(trim((string)$textNode)) > 0) {
                                $result['html'] = $textNode;
                            }
                        } else {

                            $newNode = array(
                                'tag' => $childNode->nodeName
                            );

                            if ($childNode->hasAttributes()) {
                                foreach ($childNode->attributes as $attr) {
                                    $newNode[$attr->name] = $attr->value;
                                    if ($attr->name === 'class' && $attr->value === $startClass) {
                                        $render = true;
                                    }
                                }
                            }

                            $children = $domToArray($childNode, $startClass, $render);
                            if (count($children) > 0) {
                                if (array_key_exists('html', $children)) {
                                    $newNode['html'] = $children['html'];
                                } else {
                                    $newNode['children'] = $children;
                                }
                            }

                            if ($startClass === null || $render === true) {
                                $result[] = $newNode;
                            }
                        }
                    }
                }
            }

            return $result;
        };

        $htmlDom = new DOMDocument();
        $htmlDom->loadHTML($html);

        $xpath = new DOMXPath($htmlDom);
        $node = $xpath->query("//*[@id='$startingTagId']")->item(0);

        return $domToArray($node);

    }

}