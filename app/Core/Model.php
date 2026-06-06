<?php

declare(strict_types=1);

namespace App\Core;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $belongsTo = [];
    protected array $hasMany = [];

    public function table(): string
    {
        return $this->table;
    }

    public function primaryKey(): string
    {
        return $this->primaryKey;
    }

    public function fillable(): array
    {
        return $this->fillable;
    }

    public function belongsTo(): array
    {
        return $this->belongsTo;
    }

    public function hasMany(): array
    {
        return $this->hasMany;
    }
}
