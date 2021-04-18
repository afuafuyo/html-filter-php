<?php
namespace Afu;

class HtmlFilter {
    // <(xxx)( data-name="lisi") xxx />
    // </(xxx)>
    // <!--(xxx)-->
    // 此正则有四个子模式
    // 1. 代表开始标签名称
    // 2. 代表整个属性部分 该部分可有可无
    // 3. 代表结束标签名称
    // 4. 代表注释内容
    private $REG_HTML = '/<(?:(?:(\w+)((?:\s+[\w\-]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^>\s]+))?)*)[\S\s]*?\/?>)|(?:\/([^>]+)>)|(?:!--([\S|\s]*?)-->))/m';
    private $REG_ATTR = '/(?:([\w\-]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^>\s]+)))?)/m';
    public static $ATTRIBUTES_EMPTY = [
        'checked' => 1,
        'compact' => 1,
        'declare' => 1,
        'defer' => 1,
        'disabled' => 1,
        'ismap' => 1,
        'multiple' => 1,
        'nohref' => 1,
        'noresize' => 1,
        'noshade' => 1,
        'nowrap' => 1,
        'readonly' => 1,
        'selected' => 1
    ];
    public static $TAGS_SELFCLOSING = [
        'area' => 1,
        'meta' => 1,
        'base' => 1,
        'link' => 1,
        'hr' => 1,
        'br' => 1,
        'wbr' => 1,
        'col' => 1,
        'img' => 1,
        'area' => 1,
        'input' => 1,
        'textarea' => 1,
        'embed' => 1,
        'param' => 1,
        'source' => 1,
        'object' => 1
    ];

    /**
     * Legal tags
     *
     * [
     *     'p' => null,  // not support attributes
     *     'img' => ['src' => 1, 'width' => 1, 'height' => 1],  // support some attributes
     *     ...
     * ]
     */
    public $allowedTags = null;
    
    public $allowComment = false;
    
    public $htmlString = '';

    /**
     * 没有指定白名单时 默认支持所有标签
     *
     * @param string $nodeName
     */
    private function isAllowedTag($nodeName) {
        if(null === $this->allowedTags) {
            return false;
        }

        // white list
        // null is exists yet
        if(array_key_exists($nodeName, $this->allowedTags)) {
            return true;
        }

        return false;
    }

    private function onText($text) {
        $this->htmlString .= $text;
    }

    private function onClose($tagName) {
        $nodeName = strtolower($tagName);

        // 非法标签
        if(!$this->isAllowedTag($nodeName)) {
            return;
        }

        $this->htmlString .= '</' . $nodeName . '>';
    }

    private function isEmptyAttribute($attribute) {
        return isset(static::$ATTRIBUTES_EMPTY[$attribute]);
    }

    private function getAllowedAttributes($nodeName) {
        if(null === $this->allowedTags) {
            return null;
        }

        // tag not in white list or tag not support attributes
        if(!isset($this->allowedTags[$nodeName]) || null === $this->allowedTags[$nodeName]) {
            return null;
        }

        return $this->allowedTags[$nodeName];
    }

    private function isSelfClosingTag($nodeName) {
        return isset(static::$TAGS_SELFCLOSING[$nodeName]);
    }

    private function onOpen($tagName, $attributes) {
        $nodeName = strtolower($tagName);
        $nodeString = '';

        // 非法标签
        if(!$this->isAllowedTag($nodeName)) {
            return;
        }

        // attributes filter
        $allowedAttributes = $this->getAllowedAttributes($nodeName);
        if(null !== $allowedAttributes) {
            foreach($attributes as $k => $v) {
                if(!isset($allowedAttributes[$k])) {
                    unset($attributes[$k]);
                }
            }
        }

        $nodeString = '<' . $nodeName;

        // null means not support attributes
        if(null !== $allowedAttributes) {
            foreach($attributes as $k => $v) {
                $nodeString .= (' ' . $k . '="' . $attributes[$k] . '"');
            }
        }

        // selfClosingTag
        if($this->isSelfClosingTag($nodeName)) {
            $nodeString .= ' /';
        }

        $nodeString .= '>';

        $this->htmlString .= $nodeString;
    }

    private function onComment($content) {
        if(!$this->allowComment) {
            return;
        }
        
        $this->onText('<!--' . $content . '-->');
    }

    public function filter($html) {
        $tagName = '';

        // 添加一个临时节点 以便进行匹配
        $subString = $html . '<htmlfilter />';
        while(1 === preg_match($this->REG_HTML, $subString, $parts, PREG_OFFSET_CAPTURE)) {
            if($parts[0][1] > 0) {
                $this->onText( substr($subString, 0, $parts[0][1]) );
                $subString = substr($subString, $parts[0][1] + strlen($parts[0][0]));

            } else {
                $subString = substr($subString, strlen($parts[0][0]));
            }

            // closing tag
            if( isset($parts[3]) && $parts[3][0] ) {
               $this->onClose( $parts[3][0] );
               continue;
            }

            if( ($tagName = $parts[1][0]) ) {
                $attrs = [];

                // attributes
                if($parts[2][0] && preg_match_all($this->REG_ATTR, $parts[2][0], $attrParts) > 0) {
                    for($i = 0; $i < count($attrParts[1]); $i++) {
                        $attrName = $attrParts[1][$i];
                        $attrValue = '';

                        if($attrParts[2][$i]) {
                            $attrValue = $attrParts[2][$i];
                        } else if($attrParts[3][$i]) {
                            $attrValue = $attrParts[3][$i];
                        } else if($attrParts[4][$i]) {
                            $attrValue = $attrParts[4][$i];
                        }

                        if($this->isEmptyAttribute($attrName)) {
                            $attrs[$attrName] = $attrName;

                        } else {
                            $attrs[$attrName] = $attrValue;
                        }
                    }
                }

                $this->onOpen($tagName, $attrs);

                continue;
            }

            // comment
            if( isset($parts[4]) && $parts[4][0] ) {
                $this->onComment($parts[4][0]);
            }
        }

        return $this->htmlString;
    }
}
