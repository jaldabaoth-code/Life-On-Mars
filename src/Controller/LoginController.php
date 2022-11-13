<?php

namespace App\Controller;

use App\Model\UserManager;

class LoginController extends AbstractController
{
    public const MAX_LOGIN_LENGTH = 50;

    public function connection()
    {
        $errors = [];
        $login = '';

        if ($_SERVER["REQUEST_METHOD"] === "POST") {
            $login = trim($_POST['login']);
            $errors = $this->validateInput($login, $errors);

            if (empty($errors)) {
                $userManager = new UserManager();
                $user = $userManager->selectOneByUsername($login);

                if ($user && $user['username'] === $login) {
                    $_SESSION['id'] = $user['id'];
                    $_SESSION['login'] = $user['username'];
                    $_SESSION['profile_picture'] = $user['profile_picture'];
                    $_SESSION['profile_certified'] = $user['profile_certified'];

                    header("Location: /Home/index");
                }
            }
        }
        return $this->twig->render('Login/connection.html.twig', [
            'login' => $login,
            'errors' => $errors
        ]);
    }

    public function validateInput(string $login, array $errors)
    {
        if (empty($login)) {
            $errors[] = 'Please enter your username';
        }

        if (strlen($login) > self::MAX_LOGIN_LENGTH) {
            $errors[] = 'Your username must be ' . self::MAX_LOGIN_LENGTH . ' characters long max.';
        }

        return $errors;
    }

    public function disconnection()
    {
        session_unset();

        header("Location: /Home/index");
    }
}
