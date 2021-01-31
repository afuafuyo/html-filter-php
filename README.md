# html-filter-php

filter html and attributes for php to prevent XSS with a configuration specified by a whitelist

```
<?php
namespace app\controllers\index;

use Afu\HtmlFilter;

class IndexController extends Controller {
    public function run() {

        $html = <<<STR
<div class="wrapper">
    <h2>这是第一段</h2>
    <p style="text-align: center">这是第一段</p>
    <blockquote data-role="danger">这是第一段</blockquote>
</div>
STR;
        $f = new HtmlFilter();
        $f->allowedTags = [
            'p' => null, // not support attributes
            'div' => ['class' => 1],  // support class attribute
            'blockquote' => ['data-role' => 1]
        ];
        echo $f->filter($html);
    }
}


// output is:
<div class="wrapper">
    这是第一段
    <p>这是第一段</p>
    <blockquote data-role="danger">这是第一段</blockquote>
</div>
```
