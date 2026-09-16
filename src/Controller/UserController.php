<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Repository\UserRepository;
use App\View\View;

final class UserController extends Controller
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function index(Request $request): View
    {
        return $this->view('users/index', [
            'title' => 'Users',
            'users' => $this->users->all(),
        ]);
    }

    /**
     * The htmx fragment endpoint. Same data, same repository, different
     * template — the request decides whether a layout is wrapped around it.
     */
    public function rows(Request $request): View
    {
        return $this->view('users/rows', ['users' => $this->users->all()]);
    }

    public function store(Request $request): View
    {
        $name = trim($request->post['name'] ?? '');
        $email = trim($request->post['email'] ?? '');

        if ($name !== '' && $email !== '') {
            $this->users->create($name, $email);
        }

        return $this->rows($request);
    }
}
