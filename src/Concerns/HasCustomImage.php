<?php

declare(strict_types=1);

namespace Eznix86\PestPluginTestContainers\Concerns;

trait HasCustomImage
{
    public function image(string $image): static
    {
        $this->setImage($image);

        return $this;
    }

    abstract protected function setImage(string $image): void;
}
