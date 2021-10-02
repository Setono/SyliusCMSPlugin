<?php

declare(strict_types=1);

namespace Setono\SyliusCMSPlugin\Filesystem;

use Gaufrette\Adapter;

abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * This is the decorated (inner) adapter
     */
    protected Adapter $adapter;

    /**
     * This is the config used to create the adapter
     *
     * @var array<string, mixed>
     */
    private array $config;

    public function __construct(Adapter $adapter, array $config)
    {
        $this->adapter = $adapter;
        $this->config = $config;
    }

    /**
     * @param string $key
     */
    public function read($key)
    {
        return $this->adapter->read($key);
    }

    /**
     * @param string $key
     * @param string $content
     */
    public function write($key, $content)
    {
        return $this->adapter->write($key, $content);
    }

    /**
     * @param string $key
     */
    public function exists($key): bool
    {
        return $this->adapter->exists($key);
    }

    public function keys(): array
    {
        return $this->adapter->keys();
    }

    /**
     * @param string $key
     */
    public function mtime($key)
    {
        return $this->adapter->mtime($key);
    }

    /**
     * @param string $key
     */
    public function delete($key): bool
    {
        return $this->adapter->delete($key);
    }

    /**
     * @param string $sourceKey
     * @param string $targetKey
     */
    public function rename($sourceKey, $targetKey): bool
    {
        return $this->adapter->rename($sourceKey, $targetKey);
    }

    /**
     * @param string $key
     */
    public function isDirectory($key): bool
    {
        return $this->adapter->isDirectory($key);
    }
}
