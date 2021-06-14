<?php

use App\Http\Controllers\BroadcastChat\BroadcastController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\WebinarAkbarController;
use App\Http\Controllers\SchoolParticipantAkbarController;
use App\Http\Controllers\NotificationWebinarController;
use App\Http\Controllers\StudentNormalWebinarParticipantController;
use App\Http\Controllers\WebinarNormalController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Payment\WebinarPaymentController;
use App\Http\Controllers\WebinarOrderController;
use App\Http\Controllers\SchoolChatBoxController;
use App\Http\Controllers\StudentChatBoxController;

Route::group(['prefix' => 'webinar-akbar'], function () {
    Route::middleware('admin')->group(function () {
        Route::post('/create', [WebinarAkbarController::class, 'addWebinar']);
        Route::delete('/delete/{webinar_id}', [WebinarAkbarController::class, 'destroyWebinar']);
        Route::post('/edit', [WebinarAkbarController::class, 'editWebinar']);
        Route::get('/', [WebinarAkbarController::class, 'listWebinar']);
        Route::get('/list-school', [SchoolParticipantAkbarController::class, 'listSchool']);
        Route::get('/webinar-by-school/{id}', [WebinarAkbarController::class, 'getWebinarBySchoolId']);
        Route::post('/update-status', [SchoolParticipantAkbarController::class, 'updateSchoolWebinar']);
        Route::get('/detail/{webinar_id}', [WebinarAkbarController::class, 'detailWebinar']);
        Route::get('/participant/{webinar_id}', [WebinarAkbarController::class, 'participantList']);
        Route::post('/detail/upload-certificate', [CertificateController::class, 'addCertificateAkbar']);
        Route::group(['prefix' => 'notification'], function () {
            Route::get('/', [NotificationWebinarController::class, 'getNotification']);
            Route::post('/read', [NotificationWebinarController::class, 'setNotificationReaded']);
        });
    });
});
Route::group(['prefix' => 'webinar-internal'], function () {
    Route::middleware('admin')->group(function () {
        Route::post('/create', [WebinarNormalController::class, 'addNormalWebinar']);
        Route::post('/edit', [WebinarNormalController::class, 'editWebinar']);
        Route::delete('/delete/{webinar_id}', [WebinarNormalController::class, 'destroyWebinar']);
        Route::get('/', [WebinarNormalController::class, 'listWebinar']);
        Route::get('/detail/{webinar_id}', [WebinarNormalController::class, 'detailNormalWebinar']);
        Route::get('/detail-list/student/{webinar_id}', [WebinarNormalController::class, 'detailNormalWebinarWithStudent']);
        Route::get('/order/detail', [WebinarOrderController::class, 'getDetailOrder']);
        Route::post('/register', [StudentNormalWebinarParticipantController::class, 'registerStudent']); //ok
        Route::post('/detail/upload-certificate', [CertificateController::class, 'addCertificate']);
        Route::group(['prefix' => 'notification'], function () {
            Route::get('/', [NotificationWebinarController::class, 'getNotification']);
            Route::post('/read', [NotificationWebinarController::class, 'setNotificationReaded']);
        });
        Route::group(['prefix' => 'payment'], function () {
            Route::get('/charge', [WebinarPaymentController::class, 'charge']);
        });
    });
});

Route::group(['prefix' => 'broadcast'], function () {
    Route::middleware('admin')->group(function () {
        Route::post('/create', [BroadcastController::class, 'create']);
        Route::get('/', [BroadcastController::class, 'listRoomBroadcast']);
    });
});
Route::group(['prefix' => 'school-chat'], function () {
    Route::middleware('admin')->group(function () {
        Route::post('/room/inbox', [SchoolChatBoxController::class, 'createChat']);
        Route::get('/room/student-list', [SchoolChatBoxController::class, 'listChat']);
        Route::delete('/delete-chat/{chat_id}', [SchoolChatBoxController::class, 'deleteChat']);
        Route::get('/room', [SchoolChatBoxController::class, 'listRoom']);
        Route::delete('/room/delete/{room_chat_id}', [SchoolChatBoxController::class, 'deleteRoom']);
    });
});
Route::group(['prefix' => 'student-chat'], function () {
    Route::post('/inbox', [StudentChatBoxController::class, 'createChatStudent']);
    Route::get('/school', [StudentChatBoxController::class, 'listOfChat']);
    Route::delete('/delete-chat/{chat_id}', [StudentChatBoxController::class, 'deleteChat']);
    Route::get('/school/detail', [StudentChatBoxController::class, 'detailSchool']);
});
