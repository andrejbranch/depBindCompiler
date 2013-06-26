<?php

include('Compressor/YuiCompressor.php');

try {
    $options = getopt('c:');
    if (!$filePath = $options['c']) {
        throw new \Exception('Missing required argument c');
    }

    if (!file_exists($filePath)) {
        throw new \Exception('File '.$filePath.' not found');
    }

    $conf = json_decode(file_get_contents('../../conf/conf.json'));

    $baseDir = $conf->baseDir; 
    $services = json_decode(file_get_contents($filePath));
    
    $js = "$(function() {"."\n";

    foreach ($services as $k => $service) {
        $js .= file_get_contents($baseDir.$service->file)."\n";
        $services[$service->id] = $service;
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
        foreach ($events as $type => $fun) {
            $js .= <<<EOF

   $('body').on('{$type}', {$service->variable}.el.selector, function(e) {
       var target = $(this);
       {$service->variable}.{$fun}(e, target);
   })
EOF;
        }
        foreach ($globalEvents as $type => $fun) {
            $js .= <<<EOF

   $('body').on('{$type}', function(e, data) {
       {$service->variable}.{$fun}(e, data);
   })

EOF;
        }
    }

    $js .= "});";

    $outputFile = file_put_contents('../../test/testcompile.js', $js);

    $yui = new YuiCompressor('Compressor/yuicompressor-2.4.jar', '../../test');
    $yui->addFile('../../test/testcompile.js');

    file_put_contents('../../test/testcompile.js', $yui->compress());
} catch(\Exception $e) {
    echo $e->getMessage()."\n";
}