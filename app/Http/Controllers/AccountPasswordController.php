<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountPasswordRequest;
use Illuminate\Http\RedirectResponse;

class AccountPasswordController extends Controller
{
    public function update(UpdateAccountPasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        return to_route('account.edit');
    }
}
