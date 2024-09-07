<?php

session_start();
include '../php/inc.php';
include '../php/data-encryption.inc.php';

$user = new user;

#
# PREVENT USERS FROM ACCESSING THIS PAGE
#

function restrict() {
    $user = new user;

    function sendToHomePage() {
        header('location: ..');
    }
    
    if (!isset($_SESSION['loggedin'])) {
        sendToHomePage();
    }
    
    if (!$user->isFaculty()) {
        sendToHomePage();
    }

    if (isRestricted()) {
        header('location: ../restrict');
    }
}

#
# INIT
#

page_init();

function page_init() {
    restrict();
    showPopups();
}



function showPopups() {
    if (isset($_SESSION['execution']) && $_SESSION['execution'] == 'successAddStudent') {
        showSuccessPopup('The Student Added Successfully');
    } 
    
    if (isset($_SESSION['execution']) && $_SESSION['execution'] == 'failedAddStudent') {
        showErrorPopup('An Error Occured.', 'This maybe because of system error or database crash');
    } 
    
    if (isset($_SESSION['execution']) && $_SESSION['execution'] == 'invalidAddStudent') {
        showInvalidPopup('Please Complete All Forms', 'Please make sure that you inputted valid forms');
    } 
    
    if (isset($_SESSION['execution']) && $_SESSION['execution'] == 'duplication') {
        showInvalidPopup('Form Duplication Found', 'The USN is already registered.');
    }

    unset($_SESSION['execution']);
}

#
# ADD STUDENT
#

if (isset($_POST['add-student'])) {
    verifyAddStudentForm();
}

function verifyAddStudentForm() {
    if (addStudentInputAreValid()) {
        checkLRNDuplication();
    } else {
        showInvalidPopup('Please Complete All Forms', 'Please make sure that you inputted valid forms');
    }
}

function checkLRNDuplication() {

    $con = connect();
    $isDuplicate = null; 

    $inputtedLRN = $_POST['lrn'];
    $stmt = $con->prepare('SELECT * FROM user_school_info WHERE lrn = ?');
    $stmt->bind_param('i', $_POST['lrn']);
    $stmt->execute();
    $stmt->store_result();
    $isDuplicate = $stmt->num_rows;
    
    addStudent();
    
}

function addStudent() {
    $con = connect();
    $activityLog = new activityLog;

    $isAllSuccess = 0;

    $isAllSuccess = addStudentCredentials($con);
    $lastInsertID = $con->insert_id;

    $isAllSuccess = addStudentBasicInfo($con, $lastInsertID);
    $isAllSuccess = addStudentFamilyBackground($con, $lastInsertID);
    $isAllSuccess = addStudentSchoolInfo($con, $lastInsertID);

    if ($isAllSuccess) 
    {
        $activityLog->recordActivityLog('registered a new student');
        $_SESSION['execution'] = 'successAddStudent';
        header('location: ?successAddStudent');
        exit(0);
    } else {
        $_SESSION['execution'] = 'failedAddStudent';
        header('location: ?failed');
        exit(0);
    }

}

function addStudentCredentials($con) {
    $lrn = titleCase(validateInput($_POST['lrn']));
    $studentType = 0;
    

    $email = generateEmail($lrn);
    $password = password_hash(generatePassword($lrn), PASSWORD_BCRYPT);

    $sql = "INSERT INTO user_credentials 
            (email, password, usertype)
            VALUES (?, ?, ?)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('sss', $email, $password, $studentType);
    $result = $stmt->execute();

    if ($result) {
        return 1;
    } else {
        return 0;
    }

}


function addStudentBasicInfo($con, $lastInsertID) {
    $con = connect();

    $firstname          = titleCase(validateInput($_POST['firstname']));
    $lastname           = titleCase(validateInput($_POST['lastname']));
    $gender             = titleCase(validateInput($_POST['gender']));
    $middlename         = titleCase(validateInput($_POST['middlename'])); 
    $birthday           = titleCase(validateInput($_POST['birthday'])); 
    $religion           = titleCase(validateInput($_POST['religion'])); 
    $country            = titleCase(validateInput($_POST['country'])); 
    $region             = titleCase(validateInput($_POST['region'])); 
    $address            = titleCase(validateInput($_POST['address'])); 
    $contact            = titleCase(validateInput($_POST['contact']));

    $sql = "INSERT INTO user_information 
            (id, firstname, middlename, lastname, 
            gender, birthday, religion, 
            country, region, address, contact)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('isssssssssi', $lastInsertID, $firstname, $middlename, $lastname, $gender, $birthday, $religion, $country, $region, $address, $contact);

    if ($stmt->execute()) {
        return 1;
    } else {
        return 0;
    }
}

function addStudentFamilyBackground($con, $lastInsertID) {
    $con = connect();

    $mothername         = titleCase(validateInput($_POST['mothername'])); 
    $motherwork         = titleCase(validateInput($_POST['motherwork'])); 
    $mothercontact      = titleCase(validateInput($_POST['mothercontact']));
    $fathername         = titleCase(validateInput($_POST['fathername'])); 
    $fatherwork         = titleCase(validateInput($_POST['fatherwork'])); 
    $fathercontact      = titleCase(validateInput($_POST['fathercontact'])); 
    $guardian           = titleCase(validateInput($_POST['guardian'])); 
    $relationship       = titleCase(validateInput($_POST['relationship'])); 
    $guardiancontact    = titleCase(validateInput($_POST['guardiancontact'])); 

    $sql = "INSERT INTO user_family_background 
            (id, mother_fullname, mother_work, mother_contact, 
            father_fullname, father_work, father_contact, 
            guardian_fullname, guardian_contact, relationship)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('ississisis', $lastInsertID, $mothername, $motherwork, $mothercontact, $fathername, $fatherwork, $fathercontact, $guardian, $guardiancontact, $relationship);

    if ($stmt->execute()) {
        return 1;
    } else {
        return 0;
    }
}

function addStudentSchoolInfo($con, $lastInsertID) {
    $con                = connect();
    $lrn                = titleCase(validateInput($_POST['lrn'])); 
    $section            = titleCase(validateInput(decryptData($_POST['section'])));

    $sql = "INSERT INTO user_school_info 
            (id, lrn, section)
            VALUES (?, ?, ?)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('iii', $lastInsertID, $lrn, $section);

    if ($stmt->execute()) {
        return 1;
    } else {
        return 0;
    }
}

function generateEmail($lrn, $domain = 'aiet.edu') {
    $cleaned_lrn = strtolower(str_replace(' ', '', $lrn));
    return $cleaned_lrn . '@' . $domain;
}


function generatePassword($lrn) {
    $year = date('Y');
    $password = $lrn . $year; // Concatenate LRN and current year
    return $password;
}


function populateSectionSelection() {
    $con = connect();

    $stmt = $con->prepare(
        'SELECT * FROM sections
        INNER JOIN strands ON sections.strand_id = strands.strand_id'
    );
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        ?>
        <option value="<?php echo encryptData($row['id']); ?>"><?php echo $row['section_name'] . " - " . $row['strand_name'] ?></option>
        <?php
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

    <link rel="shortcut icon" href="../favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../js/aos/dist/aos.css">
    <link rel="stylesheet" href="../styles/query.css">
    <link rel="stylesheet" href="../styles/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../styles/popup.css">
    <link rel="stylesheet" href="../styles/modal.css">
    <link rel="stylesheet" href="styles/index.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alvas | Add Student</title>

</head>
<body>

    <section id="main-UI">
    <aside class="sidebar-pane">
            <div class="container-fluid">
                <button class="show-side-bar side-bar-close" type="button"><i class="fas fa-times"></i></button>
                <div class="sidebar-pane-wrapper">

                    <div data-aos="fade-left" data-aos-duration="0900" class="sidebar-pane-userinfo">
                        <div class="sidebar-pane-userinfo-profile-container">
                            <img src="<?php echo fetchProfilePicture('../'); ?>" alt="AU">
                        </div>
                        <div class="sidebar-pane-userinfo-name">
                            <h4><?php echo $user->getFullName(); ?></h4>
                            <p>Faculty Admin</p>
                        </div>
                    </div>

                    <div class="sidebar-pane-lists">
                        <div class="container-fluid">
                            <h1 class="list-title">Administrator</h1>

                            <div class="sidebar-link">
                                <a href="../faculty"><i class="fas fa-home"></i> <span>Faculty Portal</span></a>
                            </div>
                            <div class="sidebar-link active-link">
                                <a href="../addstudent"><i class="fas fa-user-plus"></i> <span>Add Students</span></a>
                            </div>
                            <div class="sidebar-link">
                                <a href="../addpersonnel"><i class="fas fa-user-shield"></i> <span>Add Personnel</span></a>
                            </div>
                            <div class="sidebar-link">
                                <a href="../managesections"><i class="fas fa-th-list"></i> <span>Manage Sections</span></a>
                            </div>
                            <div class="sidebar-link">
                                <a href="../managesubjects"><i class="fas fa-book"></i> <span>Manage Subjects</span></a>
                            </div>
                            <div class="sidebar-link">
                                <a href="../managestrands"><i class="fas fa-layer-group"></i> <span>Manage Branch</span></a>
                            </div>
                            <div class="sidebar-link">
                                <a href="../manageteachers"><i class="fas fa-chalkboard-teacher"></i> <span>Manage Teachers</span></a>
                            </div>
                            <div class="sidebar-link">
                                <a href="../inactivestudents"><i class="fas fa-ban"></i> <span>Inactive Students</span></a>
                            </div>

                            <h1 class="list-title">Personal Settings</h1>

                            <div class="sidebar-link">
                                <a href="../settings"><i class="fas fa-cog"></i> <span>Account Settings</span></a>
                            </div>
                                                        
                        </div>
                    </div>
                </div>
            </div>
        </aside>
        <div class="main-pane">
            <nav>
                <div class="container">
                    <div class="main-pane-topper">

                        <div class="topper-left">
                            <h1>Add Student</h1>
                        </div>

                        <div class="topper-right">
                            <form action="" method="post">
                                <button class="show-side-bar" type="button"><i class="fas fa-bars"></i></button>
                            </form>
                            <form action="" method="post">
                                <button name="signout" type="submit"><i class="fad fa-sign-out"></i></button>
                            </form>
                        </div>

                    </div>
                </div>
            </nav>

           

            <section id="dots-indicator">
                <div class="container">
                    <div class="wrapper">
                        <i class="fas indicator fa-circle"></i>
                        <i class="fas indicator fa-circle"></i>
                        <i class="fas indicator fa-circle"></i>
                    </div>
                </div>
            </section>

            <form id="add-student-form" action="" method="post">
                <div class="container">
                    <!-- BASIC INFORMATION -->
                    <div class="information-page">
                        <h2> <i class="fad fa-user"></i> BASIC INFORMATION</h2>
                        <fieldset>
                            <label>Given Name</label>
                            <input type="text" name="firstname" placeholder="First name" value="<?php if (isset($_POST['firstname'])) { echo $_POST['firstname']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Middle Name</label>
                            <input type="text" name="middlename" placeholder="Middle Name (optional)" value="<?php if (isset($_POST['middlename'])) { echo $_POST['middlename']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Last Name</label>
                            <input type="text" name="lastname" placeholder="lastname" value="<?php if (isset($_POST['lastname'])) { echo $_POST['lastname']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Gender</label>
                            <div class="linear-input">
                            <fieldset>
                                <label>Male</label>
                                <input type="radio" value="Male" name="gender" <?php if (isset($_POST['gender']) && $_POST['gender'] == "Male") { echo "checked"; } ?>>
                            </fieldset>
                            
                            <fieldset>
                                <label>Female</label>
                                <input type="radio" value="Female" name="gender" <?php if (isset($_POST['gender']) && $_POST['gender'] == "Female") { echo "checked"; } ?>>
                            </fieldset>
                            </div>
                        </fieldset>

                        <div class="linear-input">
                            <fieldset>
                                <label>Birthday</label>
                                <input type="date" name="birthday" id="birthday" value="<?php if (isset($_POST['birthday'])) { echo $_POST['birthday']; } ?>">
                            </fieldset>
                            
                            <fieldset>
                                <label>Age</label>
                                <input type="number" min="16" max="60" name="age" id="age" placeholder="Provide birthday first." disabled value="<?php if (isset($_POST['age'])) { echo $_POST['age']; } ?>">
                            </fieldset>
                        </div>

                        <fieldset>
                            <label>Religion</label>
                            <select name="religion">
                                <option value="" <?php if (isset($_POST['religion']) && $_POST['religion'] == "") { echo "selected=\"selected\""; } ?>>Select religion</option>
                                <option value="Hindu" <?php if (isset($_POST['religion']) && $_POST['religion'] == "Hindu") { echo "selected=\"selected\""; } ?>>Hindu</option>
                                <option value="Muslim" <?php if (isset($_POST['religion']) && $_POST['religion'] == "Muslim") { echo "selected=\"selected\""; } ?>>Muslim</option>
                                <option value="Christian" <?php if (isset($_POST['religion']) && $_POST['religion'] == "Born Again") { echo "selected=\"selected\""; } ?>>Christian</option>
                                <option value="Other" <?php if (isset($_POST['religion']) && $_POST['religion'] == "Other") { echo "selected=\"selected\""; } ?>>Other</option>
                            </select>
                        </fieldset>

                        <div class="linear-input">
                            <fieldset>
                                <label>Country</label>
                            <input type="text" name="country" placeholder="Country" value="<?php if (isset($_POST['country'])) { echo $_POST['country']; } ?>">
                            </fieldset>

                            <fieldset>
                                <label>Region</label>
                                <input type="text" name="region" placeholder="Region" value="<?php if (isset($_POST['region'])) { echo $_POST['region']; } ?>">
                            </fieldset>
                        </div>


                        <fieldset>
                            <label>Address</label>
                            <input type="text" name="address" placeholder="Complete Address" value="<?php if (isset($_POST['address'])) { echo $_POST['address']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Contact</label>
                            <input type="number" name="contact" placeholder="Contact number (09)" value="<?php if (isset($_POST['contact'])) { echo $_POST['contact']; } ?>">
                        </fieldset>

                        <div class="button-container">
                            <button type="button" class="next-button">Next <i class="fad fa-arrow-right"></i></button>
                        </div>

                    </div>
                    <!-- FAMILY INFORMATION -->
                    <div class="information-page">
                        <h2> <i class="fad fa-house"></i> FAMILY INFORMATION</h2>
                        <fieldset>
                            <label>Mother's Name</label>
                            <input type="text" name="mothername" placeholder="Mother's  Name" value="<?php if (isset($_POST['mothername'])) { echo $_POST['mothername']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Mother Work</label>
                            <input type="text" name="motherwork" placeholder="Mother's Work" value="<?php if (isset($_POST['motherwork'])) { echo $_POST['motherwork']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Mother Contact</label>
                            <input type="number" name="mothercontact" placeholder="Mother's Contact Number (09)" value="<?php if (isset($_POST['mothercontact'])) { echo $_POST['mothercontact']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Father's Name</label>
                            <input type="text" name="fathername" placeholder="Father's Full Name" value="<?php if (isset($_POST['fathername'])) { echo $_POST['fathername']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Father's Work</label>
                            <input type="text" name="fatherwork" placeholder="Father's Work" value="<?php if (isset($_POST['fatherwork'])) { echo $_POST['fatherwork']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Father's Contact</label>
                            <input type="number" name="fathercontact" placeholder="Father's Contact (09)" value="<?php if (isset($_POST['fathercontact'])) { echo $_POST['fathercontact']; } ?>">
                        </fieldset>
                        
                        <fieldset>
                            <label>Guardian's Name</label>
                            <input type="text" name="guardian" placeholder="Guardian's Full Name" value="<?php if (isset($_POST['guardian'])) { echo $_POST['guardian']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Relationship to Guardian</label>
                            <input type="text" name="relationship" placeholder="Relationship to guaridan" value="<?php if (isset($_POST['relationship'])) { echo $_POST['relationship']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Guardian's Contact</label>
                            <input type="number" name="guardiancontact" placeholder="Guardian's Contact" value="<?php if (isset($_POST['guardiancontact'])) { echo $_POST['guardiancontact']; } ?>">
                        </fieldset>

                        <div class="button-container">
                            <button type="button" class="prev-button">Prev <i class="fad fa-arrow-left"></i></button>
                            <button type="button" class="next-button">Next <i class="fad fa-arrow-right"></i></button>
                        </div>

                    </div>

                    <div class="information-page">
                        <h2> <i class="fad fa-school"></i> SCHOOL'S DOCUMENT</h2>
                        <fieldset>
                            <label>USN</label>
                            <input type="text" name="lrn" value="<?php if (isset($_POST['lrn'])) { echo $_POST['lrn']; } ?>">
                        </fieldset>

                        <fieldset>
                            <label>Assign to Section</label>
                            <select name="section">
                                <option value="">Select</option>
                                <?php populateSectionSelection(); ?>
                            </select>
                        </fieldset>

                        <div class="button-container">
                            <button type="button" class="prev-button">Prev <i class="fad fa-arrow-left"></i></button>
                            <button type="submit" name="add-student" class="submit-button">Submit <i class="fas fa-user"></i></button>
                        </div>

                    </div>

                </div>
            </form>

        </div>
    </section>
    

</body>
</html>

<script src="../js/jquery-3.5.1.min.js"></script>
<script src="../js/aos/dist/aos.js"></script>
<script src="js/addstudent.js"></script>
<script>AOS.init();</script>
<script src="../js/sidebar.js"></script>
