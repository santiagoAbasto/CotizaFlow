<?php

declare(strict_types=1);

namespace App\Domain\Products;

enum ProductUnit: string
{
    case Unit = 'unit';
    case Piece = 'piece';
    case Kilogram = 'kg';
    case Meter = 'meter';
    case Liter = 'liter';
    case Box = 'box';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'Unidad',
            self::Piece => 'Pieza',
            self::Kilogram => 'Kilogramo',
            self::Meter => 'Metro',
            self::Liter => 'Litro',
            self::Box => 'Caja',
        };
    }
}
