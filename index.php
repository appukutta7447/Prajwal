<?php 

session_start();
include 'php/connection.php';
include 'php/input_validation.inc.php';
include 'php/users.php';
include 'php/modal.php';
include 'php/activity-log.php';


$user = new user;

#
# LANDING LINKS MANAGERS
#

if (isset($_SESSION['loggedin'])) {
    sendToRespectivePortals();
}

function sendToRespectivePortals() {
    $user = new user;

    if ($user->isStudent()) {
        header('location: student');
    } else if ($user->isTeacher()) {
        header('location: teacher');
    } else if ($user->isFaculty()) {
        header('location: faculty');
    }
}

#
# THE LOG IN FEATURE
#

if (isset($_POST['login'])) {
    prepareUserLogin();
}

function prepareUserLogin() {
    $con = connect();

    if (isset($_POST['email']) && isset($_POST['password'])) {
        $email = validateInput($_POST['email']);
        $password = validateInput($_POST['password']);
    } else {
        failedLogin();
    }

    if (isUserEmailCorrect($email) && isUserPasswordCorrect($email, $password)) {
        loginUser();
        header('location: index.php');
        exit(0);
    } else {
        failedLogin();
    }
}

function failedLogin() {
    $_SESSION['execution'] = 'failedLogin';
    $_SESSION['loginTries']--;
    $_SESSION['lastAttempt'] = time();
    header('location: ?failedLogin');
    exit(0);
}

if (isset($_SESSION['lastAttempt']) && $_SESSION['lastAttempt'] + 60 <= time()) {
    $_SESSION['loginTries'] = 3;
    unset($_SESSION['lastAttempt']);
} 

if (!isset($_SESSION['loginTries'])) {
    $_SESSION['loginTries'] = 3;
}


function setAllSessions() {
    $con = connect();

    $email = $_POST['email'];

    $sql = "SELECT * FROM user_credentials WHERE email = ?";  
    $stmt = $con->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    # ID SESSION SET
    $_SESSION['id'] = $row['id'];
    # CLEAR INVALID LOGIN SESSION
    if (isset($_SESSION['invalidLogin'])) {
        session_unset($_SESSION['invalidLogin']);  
    }  
}

function setUserStateToLogin() {
    # SET SESSION TO LOGGEDIN
    $_SESSION['loggedin'] = true;
}

function loginUser() {
    setAllSessions();
    setUserStateToLogin();
    
    $activityLog = new activityLog;
    $activityLog->recordActivityLog('has logged in.');
    $_SESSION['invalidLogin'] = false;
    $_SESSION['loginTries'] = 3;
}

function isUserPasswordCorrect($email, $password) {
    $con = connect();

    $sql = "SELECT * FROM user_credentials WHERE email = ?";  
    $stmt = $con->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return password_verify($password, $row['password']);
}

function isUserEmailCorrect($email) {
    $con = connect();

    $sql = "SELECT * FROM user_credentials WHERE email = ?";  
    $stmt = $con->prepare($sql);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows;  
}

// showWelcomerModal('ALL RIGHTS RESERVED', '&#169; 2021 Mark Kenneth S. Calendario and Group 2. <br/><br/> This system cannot be reproduced, copied and even recorded in any form or by any means without the permission of the copyright holder.');

?>

<!DOCTYPE html>
<html lang="en" UTF="8">
<head>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="js/aos/dist/aos.css">
    <link rel="stylesheet" href="styles/query.css">
    <link rel="stylesheet" href="styles/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="styles/modal.css">
    <link rel="stylesheet" href="styles/index.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome | AUSMS</title>
</head>
<body>

    <div id="loader">
        <div class="container">
            <div class="wrapper">
                <img src="assets/images/AULOGO.png" alt="">
                <h4 class="loader-main-text">The System is Loading. <br> Please wait...</h4>
                <p class="loader-sub-text">Mark Kenneth Calendario</p>
                <i class="spinner fas fa-circle-notch fa-spin"></i>
            </div>
        </div>
    </div>
    
    <header>
        <nav>
            <div class="container">
                <div class="ausms-top">
                    <div class="au-logo-top">
                        <img src="assets/images/AULOGOS.png" alt="Alvas Logo">
                    </div>
                    <div class="ausms-top-texter">
                        <h2>ALVA'S INSTITUTE OF ENGNEERING AND TECHNOLOGY</h2>
                        <h4>Student Management System</h4>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <section id = "loginUI">
        <div class="container">
            <div class="loginUI-wrapper">
                
                

                <div class="loginUI-right">
                    <div class="loginUI-right-text">
                        <h2> Log in </h2>
                    </div>
                    <div class="loginUI-right-login-panel">
                        <div class="container">
                            <?php
                            
                            if (isset($_SESSION['execution']) && $_SESSION['execution'] == 'failedLogin') {
                                ?>
                                    <h3 class="incorrect-creds"> <i class="fas fa-exclamation-circle"></i> Invalid credentials, try again.</h3>
                                    <p class="attempt-text"> Attempts Left: <?php echo $_SESSION['loginTries'] ?>  </p>
                                <?php
                                unset($_SESSION['execution']);
                            }

                            ?>
                            <form action="index.php" method="post">
                                <div class="input-contain">
                                    <label> Email </label>
                                    <div class="input-wrap">
                                        <i class="fas fa-user"></i>
                                        <input type="text" name="email">
                                    </div>
                                </div>
                                <div class="input-contain">
                                    <label> Password </label>
                                    <div class="input-wrap">
                                        <i class="fas fa-key"></i>
                                        <input id="password" type="password" name="password">
                                        <i id="showPasswordButton" class="fas fa-lock"></i>
                                    </div>
                                </div>
                                <div class="loginUI-buttons">
                                    <?php 
                                    
                                    if ($_SESSION['loginTries'] != 0) {
                                        ?>
                                            <input type="submit" name="login" value="LOGIN">
                                        <?php
                                    } else {
                                        ?>
                                            <button type="button" class="attempt-reach"> <i class="fas fa-ban"></i> Comeback again after 1 minute. </button>
                                        <?php
                                    }

                                    ?>

                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

   

</html>
<script src="js/aos/dist/aos.js"></script>
<script> AOS.init(); </script>
<script src="js/jquery-3.5.1.min.js"></script>
<script>

$(window).on('load', function() {
    $("#loader").fadeOut("slowest");;
});

$(document).ready(function () {

    $('#home-of-the-chiefs').ready(() => {

        var currentNumber = 0;

        let time = setInterval(() => {
            changeBackground();
        }, 3000);

        function changeBackground() {
            let images = [
                'volleyball_girls.jpg',
                'volleyball_boys.jpg',
                'volleyball_win.jpg',
                'basketball.jpg'
            ];

            if (currentNumber == images.length - 1) {
                currentNumber = 0;
            } else {
                currentNumber++;
            }

            let backgroundImage = images[currentNumber];

            $('#home-of-the-chiefs').css('background-image', `url(\'assets/images/home of the chiefs/${backgroundImage}\')`);
        }

    });


    $('#showPasswordButton').click(function() {
 
        if (isPasswordHidden()) {
            makePasswordVisible();

        } else {
            makePasswordHidden();
        }

        function makePasswordHidden() {
            $('#password').attr('type', 'password');

            $('#showPasswordButton')
                .removeClass('fa-unlock')
                .addClass('fa-lock')
                .css('color', 'red')
                .css('transform', 'rotate(-360deg)');
        }

        function makePasswordVisible() {
            $('#password').attr('type', 'text');

            $('#showPasswordButton')
                .removeClass('fa-lock')
                .addClass('fa-unlock')
                .css('color', 'green')
                .css('transform', 'rotate(360deg)');
        }

        function isPasswordHidden() {
            if ($('#password').attr('type') == 'password') {
                return true;
            } else {
                return false;
            }
        }
    });

    $('#iAgreePrepare').ready(function () {

        let countDownUntil = 1; // 7 Seconds
        const countDownTime = 1000; // Down every 1 second.

        let timer = setInterval(() => {
            
            if (countDownUntil >= 0) {

                $('#iAgreePrepare')
                    .html(countDownUntil)
                    .css('background-color', 'gray');
                countDownUntil--;

            } else {

                $('#iAgreePrepare')
                    .html('<i class="fad fa-handshake"></i> I Agree!')
                    .css('background-color', 'royalblue')
                    .prop('id', 'iAgree');

                clearInterval(timer);
            }

        }, countDownTime);

    });


    $('.modal-overlay').on('click', '#iAgree', function() {
        $('.modal-overlay').fadeOut('xslow');
    });

    
});

</script>
