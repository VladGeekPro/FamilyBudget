<?php

namespace App\View\Compilers;

use Illuminate\View\Compilers\BladeCompiler;

class LockedBladeCompiler extends BladeCompiler
{
    public function compile($path = null)
    {
        if ($path) {
            $this->setPath($path);
        }

        if (is_null($this->cachePath) || empty($this->getPath())) {
            parent::compile($path);

            return;
        }

        $compiledPath = $this->getCompiledPath($this->getPath());

        $this->ensureCompiledDirectoryExists($compiledPath);

        $lockHandle = @fopen($compiledPath.'.lock', 'c');

        if ($lockHandle === false) {
            parent::compile($path);

            return;
        }

        try {
            if (flock($lockHandle, LOCK_EX)) {
                clearstatcache(true, $compiledPath);
                parent::compile($path);
                flock($lockHandle, LOCK_UN);

                return;
            }
        } finally {
            fclose($lockHandle);
        }

        parent::compile($path);
    }
}
