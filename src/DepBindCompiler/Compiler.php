<?php

namespace DepBindCompiler;

use DepBindCompiler\Compressor\YuiCompressor;

class Compiler
{
    const EVENT_BIND_STRING = <<<EOF

    $('body').on('%s', %s.el.selector, function(e) {
       var target = $(this);
       %s.%s(e, target);
    })
EOF;

    const GLOBAL_EVENT_BIND_STRING = <<<EOF

    $('body').on('%s', function(e, data) {
       %s.%s(e, data);
    })

EOF;

    /**
     * project basedir
     */
    private $baseDir;

    /**
     * array of json configuration file paths
     */
    private $confs = array();

    /**
     * desired file path to compiled and minified file
     */
    private $out;

    /**
     * @var bool sets if we are currently watching for file changes
     */
    private $watching = false;

    public function __construct(array $confs, $baseDir, $out)
    {
        $this->confs = $confs;
        $this->baseDir = $baseDir;
        $this->out = $out;
    }

    public function compile($compress = false, $watch = false)
    {
        try {
            $services = array();
            $modifiedDates = array();

            foreach ($this->confs as $conf) {
                if (!file_exists($conf)) {
                    throw new \Exception('File '.$conf.' not found');
                }

                $services = array_merge($services, json_decode(file_get_contents($conf)));
            }

            $js = "$(function() {"."\n";

            foreach ($services as $k => $service) {
                $path = $this->baseDir.$service->file;
                $js .= file_get_contents($path)."\n";
                $services[$service->id] = $service;
                $modifiedDates[$service->id] = filemtime($path);

                unset($services[$k]);
            } 

            foreach ($services as $k => $service) {
                $deps = array();
                foreach ($service->arguments as $argument) {
                    $deps[] = $services[$argument]->variable;
                }
                $js .= sprintf('%s.initialize(%s);', $service->variable, implode(',', $deps))."\n";
            }

            foreach ($services as $k => $service) {
                $events = isset($service->events) ? $service->events : array();
                $globalEvents = isset($service->globalEvents) ? $service->globalEvents : array();
                $variable = $service->variable;

                foreach ($events as $type => $fun) {
                    $js .= sprintf(self::EVENT_BIND_STRING, $type, $variable, $variable, $fun);
                }

                foreach ($globalEvents as $type => $fun) {
                    $js .= sprintf(self::GLOBAL_EVENT_BIND_STRING, $type, $variable, $fun);
                }
            }

            $js .= "});";

            $outputFile = file_put_contents($this->out, $js);

            if ($compress) {
                $yui = new YuiCompressor(__DIR__.'/Compressor/yuicompressor-2.4.jar', '/tmp');
                $yui->addFile($this->out);

                file_put_contents($this->out, $yui->compress());
            }

            if ($watch) {
                while (1) {
                    sleep(1);
                    foreach ($services as $k => $service) {
                        $lastModified = filemtime($this->baseDir.$service->file);
                        if ($lastModified != $modifiedDates[$k]) {
                            $this->compile($compress, true);
                            break;
                        }
                    }

                }
            }
        } catch(\Exception $e) {
            echo $e->getMessage()."\n";
        }
    }
}