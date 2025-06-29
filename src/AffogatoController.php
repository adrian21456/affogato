<?php

namespace Zchted\Affogato;

use Illuminate\Routing\Controller as BaseController;

abstract class AffogatoController extends BaseController
{
    public function __construct() {}

    public function invoke($method, $parameters)
    {
        $result = null;
        $request = request();
        $request->merge(['parameters' => $parameters]);

        try {
            // Preload logic
            $this->preload($method, $parameters);

            // Call the actual method of the controller
            $result = parent::callAction($method, $parameters);

            // Postload logic
            $this->postload($method, $parameters);

            // Return response
            return getResponseObject($result, $request->all());
        } catch (\Exception $e) {
            // In case of exception, return error response
            return getResponseObject($result, $request->all(), $e);
        }
    }

    /**
     * @return void
     * Scripts to run before method calls
     */
    public function preload($method, $parameters) {}

    /**
     * @return void
     * Scripts to run after method calls
     */
    public function postload($method, $parameters) {}
}
