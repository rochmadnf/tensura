<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response as InertiaResponse;

class AccountController extends Controller
{
    public function edit(Request $request): InertiaResponse
    {
        return inertia('account/edit', [
            'page' => ['id' => 'account'],
            'hasAvatar' => $request->user()->avatar !== null,
        ]);
    }

    public function update(UpdateAccountProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return to_route('account.edit');
    }
}
