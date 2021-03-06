<?php
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Generates the Configuration for the Plugin
 * @return array
 */
function eupagomultibanco_config() {
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'euPago - Multibanco'
        ),
        'chave_api' => array(
            'FriendlyName' => 'Chave API',
            'Type' => 'text',
            'Size' => '20',
            'Chave cedida pelo euPago'
        ),
    );
}

/**
 * Renders and Processes
 * @param $params
 * @return string
 */
function eupagomultibanco_link($params) {
    $client = new euPagoMultibanco();
    return $client->render($params);
}

if(!class_exists('euPagoMultibanco')){
    class euPagoMultibanco{

        /**
         * Static Configuration Values
         */
        public $config = array(
            'endpoint_live' => 'https://seguro.eupago.pt/eupagov5.wsdl',
            'endpoint_sandbox' => 'http://replica.eupago.pt/replica.eupagov5_no_ssl.wsdl',
            'payment_logo' => 'https://mysite.com/assets/img/gateways/multibanco.png',
            'payment_failed' => 'https://mysite.com/assets/img/gateways/payment_failed.png',
            'table_name' => 'tbleupago_multibanco',
            'table_comment' => 'Table Created to manage Multibanco Payments with love from ecorp',
        );

        /**
         * euPagoMBWAY constructor.
         * Builds The Table if it doesnt exists
         */
        public function __construct()
        {
            $this->tableExists();
        }

        /**
         * Renders the "Link" or "form"  for the payment gateway
         * @param $params
         * @return string
         */
        public function render($params){
            # Gather and fetch data
            $key = $params['chave_api'];
            $invoice = $params['invoiceid'];
            $amount = $params['amount']; # Format: ##.##
            $data = array();

            # Check if there is already a payment requested for this invoice id
            $record = Capsule::table($this->config['table_name'])
                ->where('invoiceid',$invoice)
                ->first();

            # Already requested payment found
            if($record !== null){
                $data['entidade'] = $record->entidade;
                $data['referencia'] =  $record->referencia;
                $data['valor'] = $record->valor;
            } else{
                # Requesting Payment details from provider
                $request = $this->requestPayment($key, $invoice, $amount);
                if(is_object($request) && isset($request->entidade)){
                    $data['entidade'] = $request->entidade;
                    $data['referencia'] =  $request->referencia;
                    $data['valor'] = $request->valor;
                    # Add the record, since it didnt existed
                    $this->tableAdd(
                        array_merge(
                            $data,
                            array(
                                'invoiceid' => $invoice,
                                'orderid' => $invoice
                            )
                        )
                    );
                }
            }

            # Check if the data is valid
            if($this->isValid($data)){
                # Success Template is rendered
                return $this->getTemplate(
                    $data['entidade'],
                    $data['referencia'],
                    $data['valor']
                );
            }
            # Error Template is rendered
            return $this->getTemplateError();
        }

        /**
         * Generates MB Unique Payment ID
         * Status Available are : 0 : Success -10 : Invalid Key -9 : Invalid Values Submitted
         * @param $key
         * @param $invoiceId
         * @param $amount
         * @return mixed
         */
        public function requestPayment($key, $invoiceId, $amount) {
            try{
                # Init the SOAP Client ( Old but gold i guess lol )
                $client = @new SoapClient(
                    $this->getEndpoint($key),
                    array(
                        'cache_wsdl' => WSDL_CACHE_NONE, # Dont cache responses
                        //'connection_timeout => 15' # PHP5+ Could support this timeout! TODO : Add PHP5+Verification
                    )
                );

                # Magic Method threw soap client
                $result = $client->gerarReferenciaMB(array(
                    'chave' => $key,
                    'valor' => $amount,
                    'id' => $invoiceId
                ));

                # Check if soap is not faulty and status is the default one
                if(isset($result->estado) && $result->estado === 0 && !is_soap_fault($result)){
                    return $result;
                }
                # Handle Soap Exception/Fault in some way,
                if(is_soap_fault($result)){
                    #$error = "SOAP Fault: ( Code : {$result->faultcode}, String : {$result->faulstring} )";
                    return null;
                }
            }
            catch (\Exception $e){
                return null;
            }

            return null;
        }

        /**
         * Get the Endpoint Based on the key
         * Example : ( demo-4F5f-fDf5 ) will trigger a demo endpoint URL
         * @param $key
         * @return string
         */
        public function getEndpoint($key = ''){
            $explode = explode('-', $key);
            if (isset($explode[0]) && $explode[0] === 'demo') {
                return $this->config['endpoint_sandbox'];
            }
            return $this->config['endpoint_live'];
        }

        /**
         * Add a record to this table
         * @param $data
         * @return bool
         */
        public function tableAdd($data){
            if( $this->tableExists() && !$this->tableRecordExists($data) ){
                try{
                    Capsule::table($this->config['table_name'])->insert($data);
                    return true;
                }
                catch (\Exception $e){
                    return false;
                }
            }
            return false;
        }

        /**
         * Check if a payment is already logged for tha invoice
         * @param $data
         * @return bool
         */
        public function tableRecordExists($data){
            $count = Capsule::table($this->config['table_name'])->where([
                ['invoiceid','=', $data['invoiceid']],
                ['orderid', '=', $data['orderid']],
                ['entidade', '=', $data['entidade']],
                ['referencia', '=', str_replace(' ', '', $data['referencia'])],
                ['valor', '=', $data['valor']],
            ])->count();
            return $count >= 1;
        }

        /**
         * Checks if the table exists
         * @return bool
         */
        public function tableExists(){
            if(Capsule::schema()->hasTable($this->config['table_name'])){
                return true;
            }
            return $this->tableCreate();
        }

        /**
         * Creates the table
         * @return bool
         */
        public function tableCreate(){
            try {
                # Create the actual table
                Capsule::schema()->create(
                    $this->config['table_name'],
                    function ($table) {
                        /** @var \Illuminate\Database\Schema\Blueprint $table */
                        $table->increments('id');
                        $table->string('invoiceid',255);
                        $table->integer('entidade');
                        $table->integer('referencia');
                        $table->decimal('valor',10,2);
                        $table->integer('orderid');
                        $table->integer('estado')->default(0);
                        $table->timestamp('dataencomenda')->useCurrent();
                        $table->timestamp('datapago')->nullable();
                    }
                );
                # Comment the table, just because...ya..
                Capsule::connection()->statement("ALTER TABLE `{$this->config['table_name']}` comment '{$this->config['table_comment']}'");
                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        /**
         * Returns the Payment Table Template
         * @param $entity
         * @param $reference
         * @param $amount
         * @return string
         */
        public function getTemplate($entity,$reference,$amount){
            $template = '
            <small class="small-text" style="font-size:10px;">
                    '.Lang::trans('paymentmb_instructions').'
            </small>
            <table style="margin-top:10px;" width="60%" cellspacing="1" align="">
            <tr>
                <td colspan="2" align="center">
                    <img src="'.$this->config['payment_logo'].'" alt="'.Lang::trans('paymentmb_name').'" height="50px" />
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center">
                   &nbsp;
                </td>
            </tr>
            <tr>
                <td align="left" style="font-size:small;font-weight:bold;padding-left:15px;">'.Lang::trans('paymentmb_ent').':</td>
                <td align="left" style="font-size:small;">' . $entity . '</td>
            </tr>
            <tr>
                <td align="left" style="font-size:small;font-weight:bold;padding-left:15px;">'.Lang::trans('paymentmb_ref').':</td>
                <td align="left" style="font-size:small;">' . $reference . ' </td>
            </tr>
            <tr>
                <td align="left" style="font-size:small;font-weight:bold;padding-left:15px;">'.Lang::trans('paymentmb_amount').':</td>
                <td align="left" style="font-size:small;">' . $amount . ' EUR</td>
            </tr>
            </table>';
            return $template;
        }

        public function getTemplateError(){
            $template = '
            <table style="margin-top: 10px;" width="60%" cellspacing="0" align="center">
             <tr>
                <td colspan="2" align="center">
                  <img src="'.$this->config['payment_failed'].'" width="50px">
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center">
                   <p style="font-size:12px;">'.Lang::trans('payment_failed').'</p>
                </td>
            </tr>
            </table>';
            return $template;
        }

        /**
         * Check if the data given is valid
         * @param $data
         * @return bool
         */
        public function isValid($data){
            if(
                !empty($data['entidade']) &&
                !empty($data['referencia']) &&
                !empty($data['valor']) &&
                isset($data['entidade'],$data['referencia'],$data['valor'])
            ){
                return true;
            }
            return false;
        }
    }
}