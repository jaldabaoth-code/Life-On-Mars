<?php

namespace App\Controller;

use Amp\Success;
use App\Model\MessageManager;
use App\Model\UserManager;
use App\Model\CertifiedManager;
use App\Model\UserMessageManager;
use App\Model\PhotoManager;

class HomeController extends AbstractController
{
    private const TEXTLENGTH = 280;

    public function index()
    {
        $requester = new CertifiedManager();
        $apod = $requester->apod();
        // All messages from database (for each message it's data with all information of message)
        $messageManager = new MessageManager();
        $messages = $messageManager->selectAllMessageUsers('post_date', 'DESC');
        // Get information after form send (marser), like success, errors or data
        $marser = $this->marser();
        return $this->twig->render('Home/index.html.twig', [
            'apod' => $apod,
            'messages' => $messages,
            'success' => $marser['success'],
            'data' => $marser['data'],
            'errors' => $marser['errors'],
            'SESSION' => $_SESSION
        ]);
    }

    public function show()
    {
        $error = "";
        // Messages search form
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Get the name the user want to search
            $name = array_map('trim', $_POST);
        }
        // If user searched a name
        if (!empty($name)) {
            // Get user from database with the searched name
            $userManager = new UserManager();
            $user = $userManager->selectByUsername($name['username']);
            // If user is found in database
            if (!empty($user)) {
                // Get user messages from database
                $messageManager = new MessageManager();
                $userMessages = $messageManager->selectByUserId($user['id']);
                // Return user messages, user and SESSION in View
                return $this->twig->render('Home/show.html.twig', [
                    'userMessages' => $userMessages,
                    'user' => $user,
                    'SESSION' => $_SESSION
                ]);
            } else {
                // Clean userMessage and user variable and fill error message
                $error = "The user don't exist";
                $userMessages = null;
                $user = null;
            }
        }
        return $this->twig->render('Home/show.html.twig', [
            'error' => $error,
            'SESSION' => $_SESSION
        ]);
    }

    public function marser()
    {
        $message = '';
        $data = [];
        $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // If user is not connected redirect to login page
            if (empty($_SESSION['id'])) {
                header("Location: /login/connection");
            }
            $data = array_map('trim', $_POST);
            // If empty message
            if (empty($data['content'])) {
                $errors[] = 'A message is required';
            }
            $errors = array_merge($errors, $this->validate($data));
            // Get message and user id
            if (!empty($_SESSION)) {
                $data['user_id'] = $_SESSION['id'];
            }
            $fileNameNew = '';
            if (!empty($_FILES['files']['name'][0])) {
                // Photo information
                $files = $_FILES['files'];
                $uploaded = array();
                $failed = array();
                $allowed = array('jpg', 'png', 'webp', 'gif');
                foreach ($files['name'] as $position => $fileName) {
                    $fileTemp = $files['tmp_name'][$position];
                    $fileSize = $files['size'][$position];
                    $fileError = $files['error'][$position];
                    $fileExt = explode('.', $fileName);
                    $fileExt = strtolower(end($fileExt));
                    // If photo extension is 'jpg', 'png', 'webp' or 'gif' or fill error variable with message
                    if (in_array($fileExt, $allowed)) {
                        // If photo have no error or fill error variable with message
                        if ($fileError === 0) {
                            // If photo size is -1Mo or fill error variable with message
                            if ($fileSize <= 1000000) {
                                // Photo new (generate) name
                                $fileNameNew = uniqid('', true) . '.' . $fileExt;
                                // Destination of new (generate) photo
                                $fileDestination = 'uploads/' . $fileNameNew;
                                // Move uploaded photo to destination from temp or fill error variable with message
                                if (move_uploaded_file($fileTemp, $fileDestination)) {
                                    $uploaded[$position] = $fileDestination;
                                } else {
                                    $failed[$position] = "[{$fileName}] failed to upload.";
                                }
                            } else {
                                $failed[$position] = "[{$fileName}] is too large.";
                            }
                        } else {
                            $failed[$position] = "[{$fileName}] errored with code {$fileError}.";
                        }
                    } else {
                        $failed[$position] = "[{$fileName}] file extension '{$fileExt}' is not allowed.";
                    }
                }
                // Display the error message
                if (!empty($failed)) {
                    print_r($failed);
                }
            }
            // If there are no errors 
            if (empty($errors)) {
                // If photo new name is generated and user is logged 
                if (!empty($fileNameNew && !empty($_SESSION['id']))) {
                    // Get photo id
                    $photoManager = new PhotoManager();
                    $idPhoto = $photoManager->insert($fileNameNew, $_SESSION['id']);
                }
                $messageManager = new MessageManager();
                $userMessages = $messageManager->insert($data, $idPhoto);
                $message = 'Your message has been sent';
                // Clean the data
                $data = null;
            }
        }
        $marser = [
            'success' => $message,
            'data' => $data,
            'errors' => $errors,
        ];
        return $marser;
    }

    /* Check message length */
    private function validate(array $data): array
    {
        // Message from form should be short
        $errors = [];
        if (strlen($data['content']) > self::TEXTLENGTH) {
            $errors[] = 'The message must be less than ' . self::TEXTLENGTH . ' characters';
        }
        return $errors;
    }

    /* Add or remove likes */
    public function add(int $id)
    {
        $userMessageManager = new UserMessageManager();
        // Get message (where star has been added) by id
        $messageManager = new MessageManager();
        $message = $messageManager->selectOneById($id);
        // If user is logged
        if (!empty($_SESSION)) {
            // Get user's like of message
            $userlike = $userMessageManager->selectOne($id, $_SESSION['id']);
            // If the message was never liked (at start there is empty like and not 0 like)
            if (empty($userlike)) {
                $userMessageManager->insert($id, $_SESSION['id'], true);
                $message["likescounter"] += 1;
                $messageManager->updateLikescounter($id, $message["likescounter"]);
            } else {
                // If already liked by user then lessen otherwise increase
                if ($userlike['user_like'] == 1) {
                    $message["likescounter"] -= 1;
                    $messageManager->updateLikescounter($id, $message["likescounter"]);
                    $userMessageManager->updateUserlike($id, $_SESSION['id'], false);
                } elseif ($userlike['user_like'] == 0) {
                    $message["likescounter"] += 1;
                    $messageManager->updateLikescounter($id, $message["likescounter"]);
                    $userMessageManager->updateUserlike($id, $_SESSION['id'], true);
                }
            }
        }
        // Redirect to home index
        header("Location: /Home/index");
    }
}
