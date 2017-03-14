<?php
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Generates the Configuration for the Plugin
 * @return array
 */
function eupagopaysafecard_config() {
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'euPago - Paysafecard'
        ),
        'chave_api_pf' => array(
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
function eupagopaysafecard_link($params) {
    $client = new euPagoPaysafecard();
    return $client->render($params);
}

if(!class_exists('euPagoPaysafecard')){
    class euPagoPaysafecard{

        /**
         * Static Configuration Values
         */
        public $config = array(
            'endpoint_live' => 'https://seguro.eupago.pt/eupagov5.wsdl',
            'endpoint_sandbox' => 'http://replica.eupago.pt/replica.eupagov5.wsdl',
            'payment_logo' => 'https://my.xxxx.pt/assets/img/gateways/paysafecard.png',
            'payment_failed' => 'https://my.xxxx.pt/assets/img/gateways/payment_failed.png',
            'payment_done' => 'https://my.xxxx.pt/assets/img/gateways/payment_done.png',
            'table_name' => 'tbleupago_paysafecard',
            'table_comment' => 'Table Created to manage Paysafecard Payments with love from ecorp',
        );

        public $invoice_data;

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
            $key = $params['chave_api_pf'];
            $invoice = $params['invoiceid'];
            $amount = $params['amount']; # Format: ##.##
            $data = array();

            # Since we need those later for request the payment
            $this->invoice_data = $params;

            # Check if there is already a payment requested for this invoice id
            $record = Capsule::table($this->config['table_name'])
                ->where('invoiceid',$invoice)
                ->first();

            echo "Im here";
            var_dump($record);
            # Already requested payment found
            if($record !== null){
                $data['url'] = $record->url;
                $data['referencia'] =  $record->referencia;
                $data['valor'] = $record->valor;
            } else{
                # Requesting Payment details from provider
                echo "Im here";
                $request = $this->requestPayment($key, $invoice, $amount);
                if(is_object($request) && isset($request->referencia)){
                    $data['url'] = $request->url;
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
                    $data['url'],
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

                $post_data = array(
                    'chave' => $key,
                    'valor' => $amount,
                    'id' => $invoiceId,
                    'url_retorno' => $this->invoice_data['systemurl'].'viewinvoice.php?id='.$this->invoice_data['invoiceid'], # Callback url
                    'nome' => $this->invoice_data['clientdetails']['firstname'].' '.$this->invoice_data['clientdetails']['lastname'],
                    'email' => $this->invoice_data['clientdetails']['email'],
                    'lang' => 'pt'
                );

                # Magic Method threw soap client
                $result = $client->pedidoPF($post_data);

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
                ['url', '=', $data['url']],
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
                        $table->string('url',255);
                        $table->string('referencia',255);
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
         * @param $url
         * @param $reference
         * @param $amount
         * @return string
         */
        public function getTemplate($url,$reference,$amount){
            $template = '
            <small class="small-text" style="font-size:10px;">
                    '.Lang::trans('paymentpaysafe_instructions').'
            </small>
            <table style="margin-top:10px;" width="200px" cellspacing="1" align="">
            <tr>
                <td colspan="2" align="center">
                    <img src="'.$this->config['payment_logo'].'" alt="'.Lang::trans('paymentpaysafe_name').'" height="50px" />
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center">
                   &nbsp;
                </td>
            </tr>
            <tr>
                <td align="left" style="font-size:small;font-weight:bold;padding-left:15px;">'.Lang::trans('paymentpaysafe_url').':</td>
                <td align="left" style="font-size:small;">' . $url . '</td>
            </tr>
            <tr>
                <td align="left" style="font-size:small;font-weight:bold;padding-left:15px;">'.Lang::trans('paymentpaysafe_ref').':</td>
                <td align="left" style="font-size:small;">' . $reference . ' </td>
            </tr>
            <tr>
                <td align="left" style="font-size:small;font-weight:bold;padding-left:15px;">'.Lang::trans('paymentpaysafe_amount').':</td>
                <td align="left" style="font-size:small;">' . $amount . ' EUR</td>
            </tr>
            <tr><td align="center" style="font-size:small;font-weight:bold;">&nbsp;</td></tr>
            <tr><td align="center" style="font-size:small;"><button type="submit" value="'.Lang::trans('invoicespaynow').'" style="btn btn-sm btn-default">'.Lang::trans('invoicespaynow').'</button></td></tr>
            </table>';
            return $template;
        }

        /**
         * Returns the Error Template
         * @return string
         */
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
         * Returns the Error Template
         * @return string
         */
        public function getTemplateDone(){
            $template = '
            <table style="margin-top: 10px;" width="60%" cellspacing="0" align="center">
            <tr>
                <td colspan="2" align="center">
                  <img src="'.$this->config['payment_done'].'" width="50px">
                </td>
            </tr>
            <tr>
                <td colspan="2" align="center">
                   <p style="font-size:13px;"><strong>'.Lang::trans('payment_done').'</strong></p>
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
                !empty($data['url']) &&
                !empty($data['referencia']) &&
                !empty($data['valor']) &&
                isset($data['url'],$data['referencia'],$data['valor'])
            ){
                return true;
            }
            return false;
        }
    }
}