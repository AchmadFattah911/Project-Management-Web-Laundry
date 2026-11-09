<?php

namespace App\Http\Requests\Panel;

use App\Data\ExpenseData;
use App\Support\BusinessCalendar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id'      => ['required', 'integer', Rule::exists('expense_categories', 'id')->where('is_active', true)],
            'amount'           => ['required', 'integer', 'min:1', 'max:1000000000'],
            'expense_date'     => ['required', 'date_format:Y-m-d', 'before_or_equal:' . BusinessCalendar::today()],
            'description'      => ['required', 'string', 'min:5', 'max:1000'],
            // Foto/PDF nota wajib: validasi isi file (mime dari konten), bukan hanya ekstensi
            'receipt'          => ['required', 'file', 'mimes:' . implode(',', config('finance.expense.receipt_mimes')), 'max:' . (int) config('finance.expense.receipt_max_kb')],
            'submission_token' => ['required', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id'  => 'kategori',
            'amount'       => 'nominal',
            'expense_date' => 'tanggal',
            'description'  => 'deskripsi',
            'receipt'      => 'foto nota',
        ];
    }

    public function toData(): ExpenseData
    {
        return ExpenseData::fromArray($this->validated());
    }
}
