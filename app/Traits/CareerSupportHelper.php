<?php
namespace App\Traits;

use App\Enums\HttpCode;
use App\Enums\PostgresCode;
use Closure;
use finfo;
use GuzzleHttp\Client;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

trait CareerSupportHelper {
    use Localization;

    public static function django_password_verify(string $password, string $djangoHash): bool
    {
        $pieces = explode("$", $djangoHash);

        $iterations = $pieces[1];
        $salt = $pieces[2];
        $old_hash = $pieces[3];

        $hash = hash_pbkdf2("SHA256", $password, $salt, $iterations, 0, true);
        $hash = base64_encode($hash);

        if ($hash == $old_hash) {
            // login ok.
            return true;
        } else {
            //login fail       
            return false;
        }
    }

    public static function django_password_make($password)
    {
        $algorithm = "pbkdf2_sha256";
        $iterations = 150000;

        $newSalt = random_bytes(16);
        $newSalt = base64_encode($newSalt);

        $hash = hash_pbkdf2("SHA256", $password, $newSalt, $iterations, 0, true);
        $toDBStr = $algorithm . "$" . $iterations . "$" . $newSalt . "$" . base64_encode($hash);

        // This string is to be saved into DB, just like what Django generate.
        return $toDBStr;
    }

    /**
     * Create Random Alpanumeric
     *
     * @param int $max maximum length character
     * @return string Random string
     */
    public static function randomAlphanumeric(int $max = 10)
    {
        $permitted_chars = str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
        return substr(str_shuffle($permitted_chars), 0, $max);
    }

    /**
     * Create Random Numeric
     *
     * @param int $max maximum length character
     * @return string Random string
     */
    public static function randomNumeric(int $max = 6)
    {
        $permitted_chars = str_shuffle('0123456789');
        return substr(str_shuffle($permitted_chars), 0, $max);
    }

    /**
     * Create Random Alphabet
     *
     * @param int $max maximum length character
     * @return string Random string
     */
    public static function randomAlpha(int $max = 6)
    {
        $permitted_chars = str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');
        return substr(str_shuffle($permitted_chars), 0, $max);
    }

    /**
     * Response default exception
     * @param Throwable $th
     */
    public function responseException(Throwable $th, Request $request=null)
    {
        $code = 500;
        $response = null;
        if($th instanceof QueryException){
            $response['message'] = "Iternal database error";
            if(env("APP_DEBUG")){
                $response['query'] = $th->getMessage();
            }
            $code = 500;
        }else if ($th instanceof TokenInvalidException || $th instanceof TokenExpiredException){
            $response['message'] = $th->getMessage();
            if(env("APP_DEBUG")){
                $response['tracer'] = $th->getTrace();
            }
            $code = 401;
        }else{
            $response['message'] = $th->getMessage();
            if(env("APP_DEBUG")){
                $response['tracer'] = $th->getTrace();
            }
            $code = $th->getCode()===400?400:500;
        }

        Log::error($th);
        if($request->isJson()){
            return response()->json($response, $code);
        }else {
            return response($response,$code);
        }
    }

    /**
     * Response default
     * @param Throwable $th
     */
    public function makeJSONResponse(Request $request, $data,int $code=200, array $options = [])
    {
        return response()->json($data, $code); exit;
    }

    /**
     * Response default exception
     * @param array|LengthAwarePaginator $pagination
     */
    public function makePaginationLayout($pagination)
    {
        if(is_array($pagination)){
            return [
                "count" =>  $pagination['total'],
                "next" =>  $pagination['next_page_url'],
                "previous" =>  $pagination['prev_page_url'],
                "results" => $pagination['data']
            ];
        }else if($pagination instanceof LengthAwarePaginator){
            return [
                "count" =>  $pagination->total(),
                "next" =>  $pagination->nextPageUrl(),
                "previous" =>  $pagination->previousPageUrl(),
                "results" => $pagination->items()
            ];
        }else throw new HttpException(HttpCode::INTERNAL_SERVER_ERROR, "invalid format pagination");
    }

    /**
     * create guzzle request URI
     * @param string $base_url microservice Base URL
     * @param string $prefix microservice prefix
     * @param Request client request
     * @return string $uri
     * @throws HttpException code 500
     */
    public static function GuzzleBuildURI(string $base_url, string $prefix, Request $request): string
    {
        if(empty($base_url))throw new HttpException(500, "URL BASE cannot be empty");

        $segmentRejectionList = ["career-support","api","v1"];

        $uri = $base_url;
        $uri .= implode('/', array_filter(array_merge(['', $prefix],$request->segments()), fn($segment)=>!in_array($segment, $segmentRejectionList)));
        if($queryStrings = $request->getQueryString()) $uri .= "?$queryStrings";

        return $uri;
    }

    /**
     * create guzzle request from laravel HTTP request
     * @method GuzzleBuildRequest
     * @return array $clientOptions
     */
    public static function GuzzleBuildRequest(Request $request, bool $useMultipart=false): array{
        $clientOptions = [];
        $useMultipart = count($request->allFiles())>0;
        if(($params = $request->all()) && !in_array(strtolower($request->method()), ['get','delete'])){
            foreach ($params as $key => $value) {
                if($useMultipart){
                    if(!isset($clientOptions['multipart'])) $clientOptions['multipart'] = [];
                    if($value instanceof UploadedFile){
                        if($value->isReadable()){
                            $clientOptions['multipart'][] = [
                                "name"      => $key,
                                "contents"  => fopen($value->getRealPath(), 'r'),
                                "filename"  => $value->getClientOriginalName(),
                            ];
                        } else throw new BadRequestHttpException("can't read the file uploaded. Code:1");
                    }else{
                        if(is_array($value)){
                            foreach ($value as $index => $data) {
                                if($data instanceof UploadedFile){
                                    if($data->isReadable()){
                                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                                        $clientOptions['multipart'][] = [
                                            "name"      => "{$key}[{$index}]",
                                            "contents"  => fopen($data->getRealPath(), 'r'),
                                            "filename"  => $data->getClientOriginalName(),
                                            "headers"   => ['Content-Type' => $finfo->file($data->getRealPath())]
                                        ];
                                    }else throw new BadRequestHttpException("can't read the file uploaded. Code:2");
                                }else{
                                    if(is_array($data)){
                                        foreach ($data as $dataIndex => $content) {
                                            if($content instanceof UploadedFile){
                                                if($content->isReadable()){
                                                    $clientOptions['multipart'][] = [
                                                        "name"      => "{$key}[{$index}][{$dataIndex}]",
                                                        "contents"  => fopen($content->getRealPath(), 'r'),
                                                        "filename"  => $content->getClientOriginalName(),
                                                    ];
                                                }else throw new BadRequestHttpException("can't read the file uploaded. Code:3");
                                            }else{
                                                if(is_array($content)){
                                                    foreach ($content as $contentIndex_1 => $content_1) {
                                                        if(is_array($content_1)){
                                                            foreach ($content_1 as $contentIndex_2 => $content_2) {
                                                                if($content_2 instanceof UploadedFile){
                                                                    if($content_2->isReadable()){
                                                                        $clientOptions['multipart'][] = [
                                                                            "name"      => "{$key}[{$index}][{$dataIndex}][{$contentIndex_1}][{$contentIndex_2}]",
                                                                            "contents"  => fopen($content_2->getRealPath(), 'r'),
                                                                            "filename"  => $content_2->getClientOriginalName(),
                                                                        ];
                                                                    }else throw new BadRequestHttpException("can't read the file uploaded. Code:4");
                                                                }else{
                                                                    $clientOptions['multipart'][] = [
                                                                        "name"      => "{$key}[{$index}][{$dataIndex}][{$contentIndex_1}][{$contentIndex_2}]",
                                                                        "contents"  => $content_2,
                                                                    ];
                                                                }
                                                            }
                                                        }else{
                                                            if($content_1 instanceof UploadedFile){
                                                                if($content_1->isReadable()){
                                                                    $clientOptions['multipart'][] = [
                                                                        "name"      => "{$key}[{$index}][{$dataIndex}][{$contentIndex_1}]",
                                                                        "contents"  => fopen($content_1->getRealPath(), 'r'),
                                                                        "filename"  => $content_1->getClientOriginalName(),
                                                                    ];
                                                                }else throw new BadRequestHttpException("can't read the file uploaded. Code:5");
                                                            }else{
                                                                $clientOptions['multipart'][] = [
                                                                    "name"      => "{$key}[{$index}][{$dataIndex}][{$contentIndex_1}]",
                                                                    "contents"  => $content_1,
                                                                ];
                                                            }
                                                        }
                                                    }    
                                                }else{
                                                    if($content instanceof UploadedFile){
                                                        if($content->isReadable()){
                                                            $clientOptions['multipart'][] = [
                                                                "name"      => "{$key}[{$index}][{$dataIndex}]",
                                                                "contents"  => fopen($content->getRealPath(), 'r'),
                                                                "filename"  => $content->getClientOriginalName(),
                                                            ];
                                                        }else throw new BadRequestHttpException("can't read the file uploaded. Code:6");
                                                    }else{
                                                        $clientOptions['multipart'][] = [
                                                            "name"      => "{$key}[{$index}][{$dataIndex}]",
                                                            "contents"  => $content,
                                                        ];
                                                    }
                                                }
                                            }
                                        }
                                    }else{
                                        $clientOptions['multipart'][] = [
                                            "name"      => "{$key}[{$index}]",
                                            "contents"  => $data,
                                        ];
                                    }
                                }
                            }
                        }else{
                            $clientOptions['multipart'][] = [
                                "name"      => $key,
                                "contents"  => $value
                            ];
                        }
                    }
                }else{
                    if(!isset($clientOptions['form_params'])) $clientOptions['form_params'] = [];
                    $clientOptions['form_params'][$key] = $value;
                }
            }
        }

        if($query = $request->query()){
            $clientOptions['query'] = $query;
        }
        
        return $clientOptions;
    }

    public function PostgresEXT_install(string $extension_name)
    {
        try {
            $results = DB::statement("CREATE EXTENSION $extension_name SCHEMA public");
            return empty($results);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function transPDOException(PDOException $exception, Closure $callback = null)
    {
        $callbackCount = Config::get('exception_callback_count', 0);
        switch ($exception->getCode()) {
            case PostgresCode::FOREIGN_KEY_VIOLATION:{
                $message = $exception->getMessage();
                $detail = explode('DETAIL:',$message);
                $detail = end($detail);
                $detail = trim(current(explode('.',$detail)));
                $detail = trim(current(explode('in table',$detail)));
                $detail = str_replace(['(',')'],'',$detail);
                throw new BadRequestHttpException($detail??$message, $exception);
                break;
            }

            case PostgresCode::UNDEFINED_FUNCTION:{
                if($this->PostgresEXT_install("dblink")) {
                    if($callbackCount++>=1){
                        throw $exception;
                        exit();
                    } else return $callback();
                }
                break;
            }
            case in_array($exception->getCode(), array_merge(
                PostgresCode::STRING_DATA_RIGHT_TRUNCATION, 
                [
                    PostgresCode::INVALID_DATETIME_FORMAT,
                    PostgresCode::CARDINALITY_VIOLATION,
                    PostgresCode::INVALID_TEXT_REPRESENTATION,
                    PostgresCode::DATATYPE_MISMATCH
                ]
            )) : {
                $start = strpos($exception->getMessage(), "ERROR:");
                $end = strpos($exception->getMessage(), "SQL:")-1;
                throw new BadRequestHttpException(trim(substr($exception->getMessage() ,$start, $end-$start)), $exception);
                break;
            }
            
            case PostgresCode::UNIQUE_VIOLATION: {
                $error = explode("DETAIL:", $exception->getMessage());
                $error = str_replace(['(',')','Key '],'',$error);
                $end = strpos(end($error), ".")-1;
                throw new BadRequestHttpException(trim(substr(end($error) ,0, $end)), $exception);
                break;
            }

            case PostgresCode::NOT_NULL_VIOLATION: {
                $error = explode("ERROR:", $exception->getMessage());
                $error = str_replace(['"'],'',$error);
                $end = strpos(end($error), "of")-1;
                throw new BadRequestHttpException(trim(substr(end($error) ,0, $end)), $exception);
                break;
            }

            case PostgresCode::SYNTAX_ERROR: {
                throw new BadRequestHttpException("ERROR Code:".PostgresCode::SYNTAX_ERROR, $exception);
                break;
            }

            default: {
                if(env("APP_DEBUG")){
                    throw $exception;
                }else{
                    $expl = explode(":", $exception->getMessage());
                    throw new HttpException(HttpCode::SERVICE_UNAVAILABLE, current($expl)??HttpCode::getTranslationByKey(HttpCode::SERVICE_UNAVAILABLE));
                }
                break;
            }
        }
    }

    function str2bin($str){
        
        # Declare both Binary variable and Prepend variable
        $bin = (string)""; $prep = (string)"";
        
        # Iterate through each character of our input ($str) 
        for($i = 0; $i < strlen($str); $i++){
            
            # Encode The current character into binary
            $bincur = decbin( ord( $str[$i] ) );
            
            # Count the length of said binary
            $binlen = strlen( $bincur );
            
            # If the length of our character in binary is less than a byte (8 bits); Then
            # For how ever many characters it is short;
            # it will replace with 0's in our Prepend variable.
            if( $binlen < 8 ) for( $j = 8; $j > $binlen; $binlen++ ) $prep .= "0"; 
            
            # Build our correct 8 bit string and add it to our Binary variable
            $bin .= $prep.$bincur." ";
            
            # Clear our Prepend variable before the next Loop
            $prep = "";
        }
        # Return the final result minus the one whitespace at the end
        # (from our for loop where we build the 8 bit string
        return substr($bin, 0, strlen($bin) - 1);
    }
    
    function bin2str($bin){
        $str = "";
        $char = explode(" ", $bin);
        foreach($char as $ch) $str .= chr(bindec($ch));
        return $str;
    }

    private function CacheRemember($key, Closure $callback)
    {
        try {
            Cache::forget($key);
            Cache::rememberForever($key, $callback);
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    /**
     * create an array
     * @param int $length
     * @param mixed $value
     */
    public function ArrCreate(int $length, $value)
    {
        $array = [];
        for ($i=0; $i < $length; $i++) { 
            $array[] = is_array($value)?$value[$i]??null:$value;
        }
        return $array;
    }

    /**
     * slipt each words and limit by length
     * @return array of string
     */
    public function word_split(string $words, int $maxLength=10)
    {
        $words = explode(' ', $words);
        $currentLength = 0;
        $index = 0;
        $output = [''];
        foreach ($words as $word) {
            // +1 because the word will receive back the space in the end that it loses in explode()
            $wordLength = strlen($word) + 1;

            if (($currentLength + $wordLength) <= $maxLength) {
                $output[$index] .= $word . ' ';
                $currentLength += $wordLength;
            } else {
                $index += 1;
                $currentLength = $wordLength;
                $output[$index] = $word;
            }
        }

        return $output;
    }
}
