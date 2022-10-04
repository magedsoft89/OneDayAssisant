<?php
namespace App\Traits;

trait ApiResponse
{
    /**
     * success response method.
     *
     * @param null $result
     * @param bool $isArray
     * @return mixed
     */
    protected function sendResponse($result = null,$isArray = true,$total=0)
    {
        $response = [
            'success' => true,
            'data' => $result == null ? '' : $result,
        ];
        if($isArray){
            $response = [
                'success' => true,
                'data' => $result == null ? [] : $result
            ];
        }
        if($total != 0){
            $response['total'] = $total;
        }
//        return $response;

        return response()->json($response, 200, ['Content-Type' => 'application/json;charset=UTF-8', 'Charset' => 'utf-8'],
            JSON_UNESCAPED_UNICODE);
    }


    /**
     * return error response.
     *
     * @param $errormessage
     * @param $code
     * @param object|null $errordata
     * @return mixed
     */
    protected function sendError($errormessage, $code, $errordata = null)
    {
        $response = [
            'success' => false,
            'message' => $errormessage,
        ];
        if ($errordata != null) {
            $response['data'] = $errordata;
        }
        return response()->json($response, $code);
    }




}
