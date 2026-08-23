<?php

namespace Eep\ValueObject;

/**
 * Representa un grupo colapsable en el sidebar.
 * Contiene uno o varios objetos Menu como hijos.
 */
class MenuGroup
{
    /** @var string */
    private $icon;

    /** @var string */
    private $name;

    /** @var Menu[] */
    private $items;

    /** @var bool  true si algún hijo está activo */
    private $active;

    /**
     * @param string $icon   Clase FontAwesome (ej. 'fa-graduation-cap')
     * @param string $name   Texto del ítem principal
     * @param Menu[] $items  Hijos del grupo
     * @param bool   $active ¿Algún hijo está activo?
     */
    public function __construct(string $icon, string $name, array $items = [], bool $active = false)
    {
        $this->icon   = $icon;
        $this->name   = $name;
        $this->items  = $items;
        $this->active = $active;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return Menu[] */
    public function getItems(): array
    {
        return $this->items;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
