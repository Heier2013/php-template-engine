<?php 
class Compile
{
    private $template;      // 待编译的文件
    private $content;       // 需要替换的文本
    private $compile;       // 替换后的文件
    private $left  = '{';   // 左定界符
    private $right = '}';   // 右定界符
    private $value = [];    // 值栈
    private $phpTurn;       // 是否允许使用PHP语法
    private $T_P = [];      // 匹配规则
    private $T_R = [];      // 替换规则

    public function __construct($template, $compileFile, $config)
    {
        $this->template = $template;
        $this->compile  = $compileFile;
        $this->content  = file_get_contents($this->template);
        
        if ($config['php_turn'] === false) {
            $this->T_P[] = "#<\?(=|php|)(.+?)\?>#is";
            $this->T_R[] = "&lt;?\\1\\2?&gt;";
        }

        $this->T_P[] = "#\{\\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\}#";
        $this->T_P[] = "#\{(loop|foreach)\\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}#i";
        $this->T_P[] = "#\{\/(loop|foreach|if)}#i";
        $this->T_P[] = "#\{([K|V])\}#";
        $this->T_P[] = "#\{if(.*?)\}#i";
        $this->T_P[] = "#\{(else|if|elseif)(.*?)\}#i";
        $this->T_P[] = "#\{(\)#";
    }

    public function c_var()
    {
        $pattern = "#\{\\$([a-zA-Z_\x7f-\xff][a-zA-Z_\x7f-\xff]*)\}#";

        if (strpos($this->content, '{$') !== false) {
            $this->content = preg_replace($pattern, "<?php echo \$this->value['\\1'];?>", $this->content);
        }
    }

    public function c_foreach()
    {
        $pattern1 = "#\{(loop|foreach)\\$(.*?)}#";
        $pattern2 = "#\{\/(loop|foreach)}#";
        $pattern3 = "#\{([K|V])\}#";

        $this->content = preg_replace($pattern1, "<?php foreach((array)\\2 as \$K=>\$V) {?>", $this->content);
        $this->content = preg_replace($pattern2, "<?php}?>", $this->content);
        $this->content = preg_replace($pattern3, "<?php echo \$\\1;?>", $this->content);
    }


}