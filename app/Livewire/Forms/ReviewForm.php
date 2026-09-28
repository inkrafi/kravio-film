<?php

namespace App\Livewire\Forms;

use Livewire\Attributes\Validate;
use Livewire\Form;

class ReviewForm extends Form
{
    #[Validate('required|string|min:3|max:5000', as: 'review')]
    public string $body = '';

    #[Validate('boolean')]
    public bool $contains_spoiler = false;
}
