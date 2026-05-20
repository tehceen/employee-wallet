<?php

namespace App\Models;

use App\Enums\WalletType;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['external_ref', 'name'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function salaryWallet(): HasOne
    {
        return $this->hasOne(Wallet::class)->where('type', WalletType::Salary);
    }
}
