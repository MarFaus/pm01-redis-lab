<?php

namespace App\Models;

use App\Services\EventCatalog;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Мероприятие. Основные данные живут в SQL-базе, Redis хранит только
 * производные вещи: кеш, счётчики и рейтинг.
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /** Категории и подписи к ним. Ключ задаёт цвет «билетной бумаги» в интерфейсе. */
    public const CATEGORIES = [
        'concert' => 'Концерт',
        'theatre' => 'Театр',
        'tech' => 'IT и наука',
        'sport' => 'Спорт',
    ];

    protected $fillable = [
        'name', 'category', 'starts_at', 'city', 'venue', 'price', 'tickets_total', 'description',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'price' => 'integer',
            'tickets_total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Данные изменились: старый кеш больше не правда, удаляем его.
        static::saved(fn (Event $event) => app(EventCatalog::class)->forget($event->id));
        static::deleted(fn (Event $event) => app(EventCatalog::class)->forget($event->id));
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
