<?php
// Require libraries needed for gateway module functions.
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
use Illuminate\Database\Capsule\Manager as Capsule;

function pv($var){
    echo "<textarea>";
    var_dump($var);
    echo "</textarea>";
}

/**
 * Class euPagoCallback
 * Note: We assume that every that the payment process hits this URL its because the payment was done!
 * We assume on this URL that payment status == paid here
 * Callback documenation is not clear so ill try to update this as soon as i get more information :)
 * TODO: Log Failed requests, improve function
 */
class euPagoCallback{

    public $request;
    public $prefix = 'eupago';
    public $prefix_table = 'tbleupago_';
    public $gateway;
    public $module;
    public $invoice;
    public $invoice_id;
    public $gateway_record;
    public $errors = array();


    /**
     * euPagoCallback constructor.
     * @param $request_data
     * Catches the request data
     */
    public function __construct($request_data)
    {
        $this->request = $request_data;
    }

    /**
     * Processes the callback
     * Just to keep it simple
     */
    public function process(){

        # Set the Module and Gateway here
        $this->setModule();
        $this->setGateway();
        $this->setGatewayRecord();
        $this->setInvoiceRecord();
        $this->validate();

        if($this->isValid()){
            $this->setPaymentDone();
            return true;
        }
        return false;
    }

    /**
     * Validates everything step by step
     */
    private function validate(){

        # 1 - Check the module
        if(!$this->isValidModule()){
            $this->errors[] = 'Invalid Module name';
        }

        # 2 -  Validate the gateway is proper installed
        if(!$this->isValidGateway()){
            $this->errors[] = 'Invalid Gateway or missing file on gateways';
        }

        # 3 - Validate the API Key
        if(!$this->isValidApiKey()){
            $this->errors[] = 'API Key is empty or invalid';
        }

        # 4 -  Validates if the table exists ( Critical )
        if(!$this->isValidTable()){
            $this->errors[] = 'Database Table for storing records for this payment processor is invalid';
            return;
        }

        # 5 - Validate if the gateway record exists with this reference and amount and status not paid ( Critical )
        if(!$this->isValidGatewayRecord()){
            $this->errors[] = 'Record  for this reference and amount could not be found';
            return;
        }

        # 6 - Validate if the invoice exists with this reference and amount and status not paid ( Critical )
        if(!$this->isValidInvoice()){
            $this->errors[] = 'Invoice for this reference and amount could not be found';
        }

        # 7 - Validates if the transaction ID is already processed ( Critical )
        if($this->isDuplicatedTransaction()){
            $this->errors[] = 'Transaction ID already processed';
        }
    }

    /**
     * Checks the gateway and gets the gateway variables
     */
    private function setGateway(){
        if($this->isValidModule()){
            $gateway = getGatewayVariables($this->prefix.$this->module);
            if(isset($gateway['type'])){
                $this->gateway = $gateway;
            }
        }
    }

    /**
     * Set the current module for payment processor
     * More like a alias maker
     */
    private function setModule(){
        switch($this->request['mp']){
            case 'PC:PT':
                $this->module = 'multibanco';
                break;
            case 'PS:PT':
                $this->module = 'payshop';
                break;
            case 'MW:PT':
                $this->module = 'mbway';
                break;
            case 'PF:PT':
                $this->module = 'paysafecard';
                break;
            case 'PQ:PT':
                $this->module = 'pagaqui';
                break;
            default:
                $this->module = null;
                break;
        }
    }

    /**
     * Populates the Gateway Record Object by the request given
     */
    private function setGatewayRecord(){
        if(isset($this->request['referencia'],$this->request['valor'])){
            $record = Capsule::table($this->getTableName())->where(array(
                ['referencia', '=', str_replace(' ', '', $this->request['referencia'])],
                ['valor', '=', $this->request['valor']],
                ['estado', '=', 0],
            ))->first();
            if($record !== null){
                $this->gateway_record = $record;
            }
        }
    }

    /**
     * Populates the Invoice Object by the request given
     */
    private function setInvoiceRecord(){
        if(isset($this->gateway_record,$this->gateway_record->orderid)){
            $record = Capsule::table('tblinvoices')->where(array(['id', '=', $this->gateway_record->orderid]))->first();
            if($record !== null){
                $this->invoice = $record;
                $this->invoice_id = $this->invoice->id;
            }
        }
    }

    /**
     * Get the database table name for this module
     * @return string
     */
    private function getTableName(){
        if(null !== $this->module){
            return $this->prefix_table.$this->module;
        }
        return '';
    }

    /**
     * Get the completed Module Name
     * @return string
     */
    private function getModuleName(){
        return $this->prefix.$this->module;
    }

    /**
     * Gets a unique Transaction ID
     * @return string
     */
    private function getTransactionId(){
        return md5(
            $this->gateway_record->id .
            $this->gateway_record->referencia .
            $this->gateway_record->valor
        );
    }

    /**
     * Get the Errors
     * @param bool $asString
     * @return array|string
     */
    public function getErrors($asString = false){
        if($asString){
            return implode(PHP_EOL,$this->errors);
        }
        return $this->errors;
    }

    /**
     * Check if the table exists in database
     * @return bool
     */
    private function isValidTable(){
        if(Capsule::schema()->hasTable($this->getTableName())){
            return true;
        }
        return false;
    }

    /**
     * Check if the transaction ID is not a
     * @return bool
     */
    private function isDuplicatedTransaction(){

        if(null !== $this->invoice){
            # Not sure why we are calling this but WHMCS docs says so.
            checkCbInvoiceID($this->invoice->id, $this->gateway['name']);
            $record = Capsule::table('tblaccounts')->where(array(
                ['transid', '=', $this->getTransactionId()],
            ))->first();
            if($record !== null){
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the module is valid
     * @return bool
     */
    private function isValidModule(){
        return $this->module !== null;
    }

    /**
     * Check if the gateway is valid
     * @return bool
     */
    private function isValidGateway(){
        return $this->gateway !== null;
    }

    /**
     * Check if the API Key is valid
     * @return bool
     */
    private function isValidApiKey(){
        if(
            isset($this->request['chave_api'],$this->gateway['chave_api']) &&
            $this->request['chave_api'] === $this->gateway['chave_api']
        ){
            return true;
        }
        return false;
    }

    /**
     * Check if the invoice is valid
     * @return bool
     */
    private function isValidInvoice(){
        return $this->invoice !== null;
    }

    /**
     * Check if the gateway record is valid
     * @return bool
     */
    private function isValidGatewayRecord(){
        return $this->gateway_record !== null;
    }

    /**
     * Check if the given data contains errors
     * @return bool
     */
    private function isValid(){
        return !(null !== $this->errors && count($this->errors) >= 1);
    }

    /**
     * Set the Payment as done
     * @return bool
     */
    private function setPaymentDone(){
        addInvoicePayment(
            $this->invoice->id,
            $this->getTransactionId(),
            $this->request['amount'],
            0,
            $this->getModuleName()
        );

        $log_data = array(
            'chave' => $this->request['chave_api'],
            'referencia' => $this->request['referencia'],
            'valor' => $this->request['valor'],
            'data' => date('Y-m-d H:i:s'),
            'invoice_id' => $this->invoice->id
        );

        logTransaction(
            $this->gateway['name'],
            $log_data,
            'Payment done successfully'
        );

        $updated = Capsule::table($this->getTableName())
            ->where([
                ['orderid', '=', $this->invoice->id],
                ['referencia', '=', $this->request['referencia']],
            ])
            ->update(array(
                'estado' => 1,
                'datapago' => date('Y-m-d H:i:s')
            ));
        return $updated >= 1;
    }
}

$callback = new euPagoCallback($_REQUEST);
$callback->process();