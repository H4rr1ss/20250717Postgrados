<?php

//CONSTANTS
use Eep\Entity\Role;
use Eep\ValueObject\View;
//CONTROLLERS
use Eep\Controller\AuthController;
use Eep\Controller\UpgCourseController;
use Eep\Controller\AssignmentController;
use Eep\Controller\InscriptionController;
use Eep\Controller\TimetableController;
use Eep\Controller\TreasuryController;
use Eep\Controller\UserController;
use Eep\Controller\CohortController;
use Eep\Controller\MassiveLoadController;
use Eep\Controller\GradesController;
use Eep\Controller\OfficialController;
use Eep\Controller\ExamenController;
use Eep\Controller\StudentGraduationController;

return [
    //FORMAT:
    // ACCION => ['view'=>LEFT_MENU, 'roles'=> [Role::UDICA_JEFE, Role1, ROLE2,....]]
    UpgCourseController::class => [
        'create' => [
            'code' => 39,
            'view' => View::UPG_COURSES,
            'roles' => [Role::DIRECTOR]
        ],
        'edit' => [
            'code' => 40,
            'view' => View::UPG_COURSES,
            'roles' => [Role::DIRECTOR]
        ],
        'delete' => [
            'code' => 41,
            'view' => View::UPG_COURSES,
            'roles' => [Role::DIRECTOR]
        ],
        'save' => [
            'code' => 42,
            'view' => View::UPG_COURSES,
            'roles' => [Role::DIRECTOR]
        ],
        'view' => [
            'code' => 43,
            'view' => View::UPG_COURSES,
            'roles' => [Role::DIRECTOR]
        ],
    ],
    AssignmentController::class => [
        'assignedCourses' => [
            'code' => 28,
            'view' => View::ASSIGNED_COURSES,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::UDICA_JEFE, Role::UDICA_OPERADOR, Role::ESTUDIANTE, Role::TESORERO],
        ],
        'assignment' => [
            'code' => 29,
            'view' => View::ASSIGNMENT,
            'roles' => [Role::ESTUDIANTE]
        ],
        'assignmentType' => [
            'code' => 30,
            'view' => View::ADMIN_ASSIGNMENT,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'adminAssignment' => [
            'code' => 31,
            'view' => View::ADMIN_ASSIGNMENT,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
    ],
    AuthController::class => [
        'noAuth' => [
            'code' => 64,
            'roles' => [Role::ALL]
        ],
        'login' => [
            'code' => 1,
            'roles' => [Role::NO_AUTH]
        ],
        'logout' => [
            'code' => 2,
            'roles' => [Role::AUTH]
        ],
        'roles' => [
            'code' => 3,
            'view' => View::ROLE_ADMIN,
            'roles' => [Role::UDICA_JEFE]
        ],
        'newRole' => [
            'code' => 4,
            'view' => View::ROLE_ADMIN,
            'roles' => [Role::UDICA_JEFE]
        ],
        'deleteRole' => [
            'code' => 5,
            'view' => View::ROLE_ADMIN,
            'roles' => [Role::UDICA_JEFE]
        ],
        'editRole' => [
            'code' => 6,
            'view' => View::ROLE_ADMIN,
            'roles' => [Role::UDICA_JEFE]
        ],
        'saveRole' => [
            'code' => 7,
            'view' => View::ROLE_ADMIN,
            'roles' => [Role::UDICA_JEFE]
        ],
    ],
    CohortController::class => [
        'cohorts' => [
            'code' => 15,
            'view' => View::COHORTS,
            'roles' => [Role::DIRECTOR]
        ],
        'createCohort' => [
            'code' => 16,
            'view' => View::COHORTS,
            'roles' => [Role::DIRECTOR]
        ],
        'deleteCohort' => [
            'code' => 17,
            'view' => View::COHORTS,
            'roles' => [Role::DIRECTOR]
        ],
        'cohortStudents' => [
            'code' => 18,
            'view' => View::COHORT_STUDENTS,
            'roles' => [Role::UDICA_JEFE, Role::UDICA_OPERADOR, Role::DIRECTOR, Role::ASISTENTE, Role::TESORERO]
        ]
    ],
    MassiveLoadController::class => [
        'index' => [
            'code' => -1,
            'roles' => [Role::PROGRAMADOR]
        ],
        'professor' => [
            'code' => -1,
            'roles' => [Role::PROGRAMADOR]
        ],
        'student' => [
            'code' => -1,
            'roles' => [Role::PROGRAMADOR]
        ],
        'timetable' => [
            'code' => -1,
            'roles' => [Role::PROGRAMADOR]
        ],
        'order' => [
            'code' => -1,
            'roles' => [Role::PROGRAMADOR]
        ],
        'assignment' => [
            'code' => -1,
            'roles' => [Role::PROGRAMADOR]
        ],
        'updateUsers' => [
            'code' => 66,
            'roles' => [Role::ALL]
        ],
        'studentInscriptions' => [
            'code' => -1,
            'roles' => [Role::PROGRAMADOR]
        ],
    ],
    GradesController::class => [
        'ponder' => [
            'code' => 49,
            'view' => View::TAUGHT_TIMETABLES,
            'roles' => [Role::CATEDRATICO]
        ],
        'view' => [
            'code' => 50,
            'view' => View::TAUGHT_TIMETABLES,
            'roles' => [Role::CATEDRATICO, Role::ASISTENTE, Role::DIRECTOR, Role::COORDINADOR]
        ],
        'complete' => [
            'code' => 51,
            'view' => View::TAUGHT_TIMETABLES,
            'roles' => [Role::CATEDRATICO]
        ],
        'entry' => [
            'code' => 52,
            'view' => View::TAUGHT_TIMETABLES,
            'roles' => [Role::CATEDRATICO]
        ],
        'generateAct' => [
            'code' => 63,
            'view' => View::TAUGHT_TIMETABLES,
            'roles' => [Role::DIRECTOR]
        ],
        'gradeDetail' => [
            'code' => 65,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::UDICA_JEFE, Role::UDICA_OPERADOR, Role::UDICA_PROGRAMADOR, Role::ESTUDIANTE],
        ],
    ],
    InscriptionController::class => [
        'view' => [
            'code' => 32,
            'view' => View::INSCRIPTION,
            'roles' => [Role::ESTUDIANTE]
        ],
        'generateOrder' => [
            'code' => 44,
            'view' => View::INSCRIPTION,
            'roles' => [Role::ESTUDIANTE]
        ]
    ],
    OfficialController::class => [
        'timetables' => [
            'code' => 53,
            'view' => View::OFFICIAL_TIMETABLES,
            'roles' => [Role::UDICA_OPERADOR, Role::UDICA_JEFE, Role::UDICA_PROGRAMADOR, Role::DIRECTOR, Role::ASISTENTE]
        ],
        'detail' => [
            'code' => 54,
            'view' => View::OFFICIAL_TIMETABLES,
            'roles' => [Role::UDICA_OPERADOR, Role::UDICA_JEFE, Role::UDICA_PROGRAMADOR, Role::DIRECTOR, Role::ASISTENTE]
        ],
        'act' => [
            'code' => 55,
            'roles' => [Role::UDICA_OPERADOR, Role::UDICA_JEFE, Role::UDICA_PROGRAMADOR, Role::DIRECTOR, Role::ASISTENTE]
        ],
        'receive' => [
            'code' => 56,
            'view' => View::OFFICIAL_TIMETABLES,
            'roles' => [Role::UDICA_OPERADOR]
        ],
        'addPostAct' => [
            'code' => 57,
            'view' => View::OFFICIAL_TIMETABLES,
            'roles' => [Role::UDICA_OPERADOR]
        ],
        'getStudents' => [
            'code' => 58,
            'roles' => [Role::UDICA_OPERADOR]
        ],
        'getCourses' => [
            'code' => 59,
            'roles' => [Role::UDICA_OPERADOR]
        ],
        'manualEntry' => [
            'code' => 60,
            'view' => View::MANUAL_OFFICIALIZATION,
            'roles' => [Role::UDICA_OPERADOR]
        ],
    ],
    TimetableController::class => [
        'taught' => [
            'code' => 48,
            'view' => View::TAUGHT_TIMETABLES,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::CATEDRATICO, Role::COORDINADOR, Role::TESORERO]
        ],
        'availableCourses' => [
            'code' => 19,
            'view' => View::AVAILABLE_COURSES,
            'roles' => [Role::AUTH]
        ],
        'categorize' => [
            'code' => 20,
            'view' => View::SCHEDULING,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'create' => [
            'code' => 21,
            'view' => View::SCHEDULING,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'delete' => [
            'code' => 22,
            'view' => View::SCHEDULING,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'edit' => [
            'code' => 23,
            'view' => View::SCHEDULING,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'save' => [
            'code' => 24,
            'view' => View::SCHEDULING,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'downloadSeason' => [
            'code' => 25,
            'view' => View::SCHEDULING,
            'roles' => [Role::UDICA_PROGRAMADOR]
        ],
        'seasonView' => [
            'code' => 26,
            'view' => View::UDICA_PROGRAMMER,
            'roles' => [Role::UDICA_PROGRAMADOR]
        ],
    ],
    TreasuryController::class => [
        'ajaxGetCoursesTimetables' => [
            'code' => 38,
            'view' => View::GRAL_REPORT,
            'roles' => [Role::UDICA_JEFE, Role::TESORERO, Role::DIRECTOR, Role::ESTUDIANTE, Role::ASISTENTE]
        ],
        'confirmOrder' => [
            'code' => 27,
            'view' => View::ORDER_LISTING,
            'roles' => [Role::ALL]
        ],
        'gralReport' => [
            'code' => 33,
            'view' => View::GRAL_REPORT,
            'roles' => [Role::UDICA_JEFE, Role::TESORERO, Role::DIRECTOR, Role::ASISTENTE]
        ],
        'orderListing' => [
            'code' => 34,
            'view' => View::ORDER_LISTING,
            'roles' => [Role::UDICA_JEFE, Role::TESORERO, Role::DIRECTOR, Role::ESTUDIANTE, Role::ASISTENTE]
        ],
        'deleteOrder' => [
            'code' => 37,
            'view' => View::ORDER_LISTING,
            'roles' => [Role::UDICA_JEFE, Role::TESORERO, Role::DIRECTOR, Role::ESTUDIANTE]
        ],
        'downloadOrder' => [
            'code' => 36,
            'view' => View::ORDER_LISTING,
            'roles' => [Role::AUTH]
        ],
        'updateOrdersStatus' => [
            'code' => 45,
            'roles' => [Role::ALL]
        ],
//        'orderUpdate' => [ //IT WASN'T DEVELOPED FOR THE LIMITED SCOPE-TIME AVAILABILITY
//            'code' => 62,
//            'view' => View::ORDERS_UPDATE,
//            'roles' => [Role::TESORERO]
//        ],
    ],
    UserController::class => [
        'profile' => [
            'code' => 8,
            'view' => View::PROFILE,
            'roles' => [Role::AUTH]
        ],
        'updateData' => [
            'code' => 46,
            'view' => View::PROFILE,
            'roles' => [Role::AUTH]
        ],
        'inscriptionTimeOver' => [
            'code' => 47,
            'view' => View::PROFILE,
            'roles' => [Role::AUTH]
        ],
        'changePassword' => [
            'code' => 9,
            'roles' => [Role::AUTH]
        ],
        'candidates' => [
            'code' => 10,
            'view' => View::CANDIDATES,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'massiveCandidates' => [
            'code' => 11,
            'view' => View::CANDIDATES,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'downloadTemplate' => [
            'code' => 12,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'studentSearch' => [
            'code' => 13,
            'view' => View::STUDENT_SEARCH,
            'roles' => [Role::UDICA_JEFE, Role::UDICA_OPERADOR, Role::DIRECTOR, Role::ASISTENTE, Role::TESORERO]
        ],
        'editUser' => [
            'code' => 14,
            'view' => View::STUDENT_SEARCH,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'logView' => [
            'code' => 35,
            'view' => View::LOG_VIEW,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::TESORERO, Role::UDICA_JEFE]
        ],
        'officialCourses' => [
            'code' => 61,
            'view' => View::OFFICIAL_COURSES,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::TESORERO, Role::UDICA_JEFE, Role::UDICA_OPERADOR, Role::UDICA_PROGRAMADOR, Role::ESTUDIANTE]
        ],
        'recoverPassword' => [
            'code' => 67,
            'roles' => [Role::NO_AUTH, Role::AUTH, Role::ALL]
        ],
    ],
    ExamenController::class => [
        'index' => [
            'code' => 68,
            'view' => View::EXAMEN,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::UDICA_JEFE]
        ],
        'papeleria' => [
            'code' => 69,
            'view' => View::EXAMEN,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::UDICA_JEFE]
        ],
        'solicitudes' => [
            'code' => 70,
            'view' => View::EXAMEN,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::UDICA_JEFE]
        ],
        'revisarpapeleria' => [
            'code' => 71,
            'view' => View::EXAMEN,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::UDICA_JEFE]
        ],
        'inscripcion' => [
            'code' => 72,
            'roles' => [Role::ESTUDIANTE]
        ],
        'subirDocumento' => [
            'code' => 100,
            'roles' => [Role::ESTUDIANTE, Role::DIRECTOR, Role::ASISTENTE]
        ],
        'guardarRevision' => [
            'code' => 101,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'guardarDocFisico' => [
            'code' => 102,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'guardarTerna' => [
            'code' => 103,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'avanzarPaso' => [
            'code' => 104,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE, Role::ESTUDIANTE]
        ],
        'notificarEstudiante' => [
            'code' => 107,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'guardarRequisito' => [
            'code' => 105,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'eliminarRequisito' => [
            'code' => 106,
            'roles' => [Role::DIRECTOR, Role::ASISTENTE]
        ],
        'cartaExaminadores' => [
            'code' => 68,
            'view' => View::CARTA_EXAMINADORES,
            'roles' => [Role::DIRECTOR, Role::COORDINADOR, Role::ASISTENTE]
        ],
        'verCarta' => [
            'code' => 68,
            'roles' => [Role::DIRECTOR, Role::COORDINADOR, Role::ASISTENTE]
        ],
    ],
    StudentGraduationController::class => [
        'index' => [
            'code' => 80,
            'view' => View::STUDENT_GRADUATION,
            'roles' => [Role::ESTUDIANTE]
        ],
        'proceso' => [
            'code' => 81,
            'view' => View::STUDENT_GRADUATION,
            'roles' => [Role::ESTUDIANTE]
        ],
        'paso1SolicitudExamen' => [
            'code' => 82,
            'view' => View::STUDENT_GRADUATION,
            'roles' => [Role::ESTUDIANTE]
        ],
        'paso2Terna' => [
            'code' => 83,
            'view' => View::STUDENT_GRADUATION,
            'roles' => [Role::ESTUDIANTE]
        ],
        'subirDocumento' => [
            'code' => 84,
            'view' => View::STUDENT_GRADUATION,
            'roles' => [Role::ESTUDIANTE]
        ],
        'verDocumento' => [
            'code' => 85,
            'roles' => [Role::ESTUDIANTE, Role::DIRECTOR, Role::ASISTENTE, Role::UDICA_JEFE]
        ],
        // ---- Paso 5: Carta de Examinadores ----
        'paso5CartaExaminadores' => [
            'code' => 68,
            'view' => View::STUDENT_GRADUATION,
            'roles' => [Role::ESTUDIANTE, Role::COORDINADOR, Role::DIRECTOR, Role::ASISTENTE]
        ],
        'subirEvidencia' => [
            'code' => 70,
            'view' => View::STUDENT_GRADUATION,
            'roles' => [Role::ESTUDIANTE]
        ],
        'aprobarTrabajo' => [
            'code' => 71,
            'view' => View::STUDENT_GRADUATION,
            'roles' => [Role::COORDINADOR, Role::DIRECTOR, Role::ASISTENTE]
        ],
        'descargarCarta' => [
            'code' => 72,
            'view' => View::STUDENT_GRADUATION,
            'roles' => [Role::ESTUDIANTE, Role::COORDINADOR, Role::DIRECTOR, Role::ASISTENTE]
        ],
        'eliminarEvidencia' => [
            'code' => 74,
            'view' => View::STUDENT_GRADUATION,
            'roles' => [Role::ESTUDIANTE]
        ],
    ],
];
