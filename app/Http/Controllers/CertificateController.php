<?php

namespace App\Http\Controllers;

use App\Jobs\CertificateJob;
use App\Jobs\CertificateAkbarJob;
use App\Models\CareerSupportModelsNormalStudentParticipants;
use App\Models\StudentModel;
use App\Models\CareerSupportModelsCertificate;
use App\Models\CareerSupportModelsOrdersWebinar;
use App\Models\CareerSupportModelsWebinarBiasa;
use App\Models\NotificationWebinarModel;
use App\Models\SchoolParticipantAkbarModel;
use App\Models\StudentParticipantAkbarModel;
use App\Models\WebinarAkbarModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\ResponseHelper;
use Exception;
use Illuminate\Support\Facades\DB;

class CertificateController extends Controller
{
    private $tbStudent;
    private $tbParticipant;
    private $tbCertficate;
    private $tbOrder;
    private $tbWebinar;
    private $tbNotification;
    private $tbWebinarakbar;
    private $tbParticipantakbar;
    private $tbSchool;
    use ResponseHelper;

    public function __construct()
    {
        $this->tbStudent = StudentModel::tableName();
        $this->tbParticipant = CareerSupportModelsNormalStudentParticipants::tableName();
        $this->tbCertficate = CareerSupportModelsCertificate::tableName();
        $this->tbOrder = CareerSupportModelsOrdersWebinar::tableName();
        $this->tbWebinar = CareerSupportModelsWebinarBiasa::tableName();
        $this->tbNotification = NotificationWebinarModel::tableName();
        $this->tbWebinarakbar = WebinarAkbarModel::tableName();
        $this->tbParticipantakbar = StudentParticipantAkbarModel::tableName();
        $this->tbSchool = SchoolParticipantAkbarModel::tableName();
    }
    public function addCertificateAkbar(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'certificate.*' => 'required|mimes:pdf|max:500',
            'webinar_id' => 'required|numeric',
        ]);
        if ($validation->fails()) {
            $this->makeJSONResponse($validation->errors(), 400);
        } else {
            try {
                if ($request->hasFile('certificate')) {
                    $certificateAll = $request->file('certificate');
                    foreach (array_slice($certificateAll, 0, 10) as $certi) {
                        $name = $certi->getClientOriginalName();
                        $nim = explode("_", $name);
                        
                        $studentId = DB::connection('pgsql2')
                            ->table($this->tbStudent)
                            ->where("nim", "=", $nim[0])
                            ->select("id as student_id", "email", "name", "school_id")
                            ->get();

                        $participantId = DB::table($this->tbParticipantakbar)
                            ->where("student_id", "=", $studentId[0]->student_id)
                            ->select("id as participant_id", "webinar_id")
                            ->get();

                        $webinar = DB::table($this->tbWebinarakbar)
                            ->where('id', '=', $request->webinar_id)
                            ->select('*')
                            ->get();

                        $school = DB::table($this->tbSchool)
                            ->where('webinar_id', '=', $request->webinar_id)
                            ->where('id', '=', $studentId[0]->school_id)
                            ->select('status')
                            ->get();

                        $path = $certi->store('certificate_akbar', 'uploads');
                        if ($school[0]->status == "5") {
                            $data =  array(
                                'certificate' => $path,
                                'webinar_akbar_id' => $participantId[0]->webinar_id,
                                'participant_akbar_id' => $participantId[0]->participant_id,
                                'file_name' => $name,
                            );

                            $notif = array(
                                'student_id'     => $studentId[0]->student_id,
                                'webinar_akbar_id' => $participantId[0]->webinar_id,
                                'message_id'    => "Selamat Anda telah mengikuti " . $webinar[0]->event_name . " pada tanggal " . $webinar[0]->event_date . " sertifikat anda telah kami kirimkan ke alamat email anda " . $studentId[0]->email,
                                'message_en'    => "Congratulation you have attended " . $webinar[0]->event_name . " on " . $webinar[0]->event_date . " your certificae had been sent to your email" . $studentId[0]->email
                            );

                            try {
                                CertificateAkbarJob::dispatch($webinar, $studentId, $data);
                                DB::table($this->tbCertficate)->insert($data);
                                DB::table($this->tbNotification)->insert($notif);
                            } catch (Exception $e) {
                                echo $e;
                            }
                        }
                    }

                    $message = "success send certificate ";
                    $code = 200;
                    return $this->makeJSONResponse(["message" => $message], $code);
                }
            } catch (Exception $e) {
                echo $e;
            }
        }
    }

    public function addCertificate(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'certificate.*' => 'required|mimes:pdf|max:500',
            'webinar_id' => 'required|numeric',
        ]);
        if ($validation->fails()) {
            $this->makeJSONResponse($validation->errors(), 400);
        } else {
            try {
                if ($request->hasFile('certificate')) {
                    $certificateAll = $request->file('certificate');
                    // ambil 10 sertifikat saja
                    foreach (array_slice($certificateAll, 0, 10) as $certi) {
                        //ambil nama sertifikat dengan format nim nama
                        $name = $certi->getClientOriginalName();
                        //split
                        $nim = explode("_", $name);
                        //terus get student_id cari berdasarkan nim nya di tabel student
                        $studentId = DB::connection('pgsql2')
                            ->table($this->tbStudent)
                            ->where("nim", "=", $nim[0])
                            ->select("id as student_id", "email", "name")
                            ->get();
                        //terus get participant_id nya dari tabel participant berdasarkan student_id
                        $participantId = DB::table($this->tbParticipant)
                            ->where("student_id", "=", $studentId[0]->student_id)
                            ->select("id as participant_id", "webinar_id")
                            ->get();

                        $orderStatus = DB::table($this->tbOrder)
                            ->where("participant_id", "=", $participantId[0]->participant_id)
                            ->select("status")
                            ->get();
                        $webinar = DB::table($this->tbWebinar)
                            ->where('id', '=', $request->webinar_id)
                            ->select('*')
                            ->get();

                        $path = $certi->store('certificate', 'uploads');

                        if ($orderStatus[0]->status == "success") {
                            // echo 'gass';
                            $data =  array(
                                'certificate' => $path,
                                'webinar_id' => $participantId[0]->webinar_id,
                                'participant_id' => $participantId[0]->participant_id,
                                'file_name' => $name,
                            );

                            $notif = array(
                                'student_id'     => $studentId[0]->student_id,
                                'webinar_normal_id' => $participantId[0]->webinar_id,
                                'message_id'    => "Selamat Anda telah mengikuti " . $webinar[0]->event_name . " pada tanggal " . $webinar[0]->event_date . " dan pada jam " . $webinar[0]->start_time . " sertifikat anda telah kami kirimkan ke alamat email anda " . $studentId[0]->email,
                                'message_en'    => "Congratulation you have attended " . $webinar[0]->event_name . " on " . $webinar[0]->event_date . " and at " . $webinar[0]->start_time . " your certificae had been sent to your email" . $studentId[0]->email
                            );

                            try {
                                CertificateJob::dispatch($webinar, $studentId, $data);
                                DB::table($this->tbCertficate)->insert($data);
                                DB::table($this->tbNotification)->insert($notif);
                            } catch (Exception $e) {
                                echo $e;
                            }
                        }
                    }
                    $message = "success send certificate ";
                    $code = 200;
                    return $this->makeJSONResponse(["message" => $message], $code);
                }
            } catch (Exception $e) {
                echo $e;
            }
        }
    }
}
