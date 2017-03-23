<?php
class Template
{
    private $arrayConfig = [
        'suffix'        => '.m',                // 设置模板文件的后缀
        'templateDir'   => 'template/',         // 设置模板所在的文件夹
        'compiledir'    => 'cache/',            // 设置编译后存放的目录
        'cache_htm'     => false,               // 是否需要编译成静态HTML文件
        'suffix_cache'  => '.htm',              // 设置编译文件的后缀
        'cache_time'    => 7200,                // 多长时间自动更新
        'php_turn'      => true,                // 是否支持原生PHP代码
        'cache_control' => 'control.dat',
        'debug'         => false
    ];

    public $file;                       // 模板文件名
    private $value = [];                // 值栈
    private $compileTool;               // 编译器
    private static $instance = null;    // 模板引擎实例

    public $debug = [];                 // 调试信息
    private $controlData = [];

    public function __construct($arrayConfig=[])
    {
        $this->debug['begin'] = microtime(true);

        // 如果键名为字符，且键名相同，数组相加会将最先出现的值作为结果
        // 区别于array_merge()
        // @links http://www.jb51.net/article/38593.htm
        $this->arrayConfig = $arrayConfig + $this->arrayConfig;

        // 路径处理为绝对路径
        $this->getPath();

        if(!is_dir($this->arrayConfig['templateDir'])) {
            exit("template dir isn't found！");
        }

        if(!is_dir($this->arrayConfig['compiledir'])) {
            mkdir($this->arrayConfig['compiledir'], 0770, true);
        }

        include('Compile.php');
    }

    /**
     * 路径处理为绝对路径
     */
    public function getPath()
    {
        $this->arrayConfig['templateDir'] = strtr(realpath($this->arrayConfig['templateDir']), '\\', '/') . '/';
        $this->arrayConfig['compiledir']  = strtr(realpath($this->arrayConfig['compiledir']), '\\', '/') . '/';
    }

    /**
     * 取得模板引擎的实例
     *
     * @return object
     * @access public
     * @static
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Template();
        }

        return self::$instance;
    }

    // 设置配置文件
    public function setConfig($key, $value=null)
    {
        if (is_array($key)) {
            $this->arrayConfig = $key + $this->arrayConfig;
        } else {
            $this->arrayConfig[$key] = $value;
        }
    }

    // 读取配置文件，仅供调试使用
    public function getConfig($key = null)
    {
        if (!is_null($key)) {
            return $this->arrayConfig[$key];
        } else {
            return $this->arrayConfig;
        }
    }

    /**
     * 注入单个变量
     *
     * @param string $key   模板变量名
     * @param mixed $value  模板变量的值
     *
     * @return void
     */
    public function assign($key, $value)
    {
        $this->value[$key] = $value;
    }

    /**
     * 注入数组变量
     *
     * @param array $array   模板变量名
     *
     * @return void
     */
    public function assignArray($array)
    {
        if (is_array($array)) {
            foreach ($$array as $key => $value) {
                $this->value[$key] = $value;
            }
        }
    }

    // 模板文件的路径
    public function path()
    {
        return $this->arrayConfig['templateDir'] . $this->file . $this->arrayConfig['suffix'];
    }

    // 判断是否开启了缓存
    public function needCache()
    {
        return $this->arrayConfig['cache_htm'];
    }

    /**
     * 是否需要重新生成静态文件
     * @param  $file 模板文件名
     * @return bool
     */
    public function reCache($file)
    {
        $flag = false;

        // 是否需要缓存
        if ($this->arrayConfig['cache_htm'] === true) {
            $cacheFile = $this->arrayConfig['compiledir'] . md5($file) . '.htm';

            // 缓存不存在
            if (!is_file($cacheFile)) {
                return true;
            }

            // 标记缓存是否过期
            $timeExpiredFlag = (time()-filemtime($cacheFile)) > $this->arrayConfig['cache_time'] ? true : false;

            // 缓存已过期
            if(filesize($cacheFile) > 1 && $timeExpiredFlag) {
                $flag = true;
            } else {
                $flag = false;
            }
        }

        return $flag;
    }

    /**
     * 显示模板
     * @param  string $file 模板文件名
     */
    public function show($file)
    {
        $this->file = $file;

        if (!is_file($this->path())) {
            exit('找不到对应的模板');
        }

        $compileFile = $this->arrayConfig['compiledir'] . md5($file) . '.php';
        $cacheFile   = $this->arrayConfig['compiledir'] . md5($file) . '.htm';

        if($this->reCache($file) === true) {
            // 标记未使用静态缓存
            $this->debug['cached'] = 'false';

            $this->compileTool = new Compile($this->path(), $compileFile, $this->arrayConfig);

            if($this->needCache()) {
                ob_start();
            }

            // 本函数用来将变量从数组中导入到当前的符号表中。 
            // 检查每个键名看是否可以作为一个合法的变量名，同时也检查和符号表中已有的变量名的冲突。 
            extract($this->value, EXTR_OVERWRITE);

            if (!is_file($compileFile) || filemtime($compileFile) < filemtime($this->path())) {

                $this->compileTool->vars = $this->value;
                $this->compileTool->compile();

                include $compileFile;
            } else {
                include $compileFile;
            }

            if($this->needCache()) {
                $message = ob_get_contents();
                file_put_contents($cacheFile, $message);
            } else {
                readFile($cacheFile);
                $this->debug['cached'] = true;
            }
        }

        $this->debug['spend'] = microtime(true) - $this->debug['begin'];
        $this->debug['count'] = count($this->value);
        
        $this->debugInfo();
    }

    public function debugInfo()
    {
        if($this->arrayConfig['debug'] === true) {
            echo PHP_EOL . '------debug info------' . PHP_EOL;
            echo '程序运行日期: ' . date('Y-m-d H:i:s') . PHP_EOL;
            echo '模板解析耗时：' . $this->debug['spend'] . '秒' . PHP_EOL;
            echo '模板包含标签数目：' . $this->debug['count'] . PHP_EOL;
            echo '是否使用静态缓存：' . $this->debug['cached'] . PHP_EOL;
            echo '模板引擎实例参数：' . var_dump($this->getConfig());
        }
    }

    /**
     * 清理缓存的HTML文件
     * @param  [type] $path [description]
     * @return [type]       [description]
     */
    public function clean($path=null)
    {
        $this->path();

        if($path == null) {
            $path = $this->arrayConfig['compiledir'];
            var_dump($path . '*' . $this->arrayConfig['suffix_cache']);
            // 寻找与模式匹配的文件路径
            $path = glob($path . '*' . $this->arrayConfig['suffix_cache']);

        } else {
            $path = $this->arrayConfig['compiledir'] . md5($path) . '.htm';
        }

        foreach ((array)$path as $value) {
            @unlink($value);
        }

        return true;
    }
}