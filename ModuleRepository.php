<?php

namespace App\Repositories;

use App\Module;
use App\ModuleCategory;
use App\ModulePrice;
use App\ModuleService;
use App\Service;
use App\TermsAndConditions;
use App\ModuleTermsAndCondition;
use App\PaymentScheduleItem;

use Carbon\Carbon;

/**
 * Class ModuleRepository
 * @package App\Repositories
 */
class ModuleRepository
{
    /**
     * @var
     */
    private $vatRateMultiplier;

    /**
     * @return float
     */
    public function getVatRateMultiplier() {
        return 1.2;
    }

    /**
     * Pass in the net price and return net, vat and gross prices
     *
     * @param $price
     * @return array
     */
    public function makeVatPrices($price) {
        return [
            'net' => $price,
            'vat' => ($price * $this->getVatRateMultiplier()) - $price,
            'gross' => $price * $this->getVatRateMultiplier(),
        ];
    }

    public function getServiceModuleIds($serviceId) {
        return $this->getServiceModules($serviceId)->pluck('id');
    }

    public function getServiceModules($serviceId) {
        return Service::find($serviceId)->modules;
    }

    public function getServiceModule($serviceId, $moduleId) {
        return ModuleService::where('service_id', $serviceId)
            ->where('module_id', $moduleId)
            ->first();
    }
    
    /**
     * Get the module contract data
     *
     * @param $isOwner
     * @param $hasTermsAndConditions
     * @return array
     */
    public function getContractData($isOwner, $hasTermsAndConditions) {

        /**
         * Role     Terms?      Status              Ordered By           Approved By            Approval Method
         * ----------------------------------------
         * Owner    No Terms    active              serviceUserId()      serviceUserId()        owner
         * Owner    Terms       active              serviceUserId()      serviceUserId()        owner
         *
         * Staff    No Terms    active              serviceUserId()                             implicit
         * Staff    Terms       pending-activation  serviceUserId()
         *
         */

        // Owner with or without Terms can order and instantly activate the module,
        // and so can A Staff member with no Terms can

        $notes = 'An Owner added (and therefore approved) the module. ';
        if ($hasTermsAndConditions) {
            $notes = 'An Owner added (and therefore approved) the module and accepted the Module Terms and Conditions. ';
        }

        // set defaults, then override
        $approvedDate = date('Y-m-d H:i:s');
        $approvedByUser = serviceUserId();
        $approvedMethod = 'owner';
        $startDate = date('Y-m-d H:i:s');
        $status = 'active';

        // Override the above values

        // A Staff member can add a module with instant access. The owner can always cancel within the given period
        if (!$isOwner) {
            $approvedByUser = null;
            $approvedMethod = 'implicit';
            $notes = 'A Staff member added the module and the Owner has been notified. ';
        }

        // A Staff member cannot accept the Terms, and therefore the module cannot be instantly activated
        if (!$isOwner && $hasTermsAndConditions) {

            $startDate = null;
            $approvedDate = null;
            $approvedByUser = null;
            $approvedMethod = null;
            $notes = 'A Staff member added the module, but cannot accept the Module Terms and Conditions.  The Owner has been notified to complete the transaction. ';
            $status = 'pending-activation';
        }

        return [
            'approved_date' => $approvedDate,
            'approved_by_user' => $approvedByUser,
            'approved_method' => $approvedMethod,
            'start_date' => $startDate,
            'status' => $status,
            'notes' => $notes,
        ];

    }

    /**
     * Does the Service have the specified Module
     *
     * @param $moduleId
     * @param array $serviceModuleIds
     * @return bool
     */
    public function hasModule($moduleId, Array $serviceModuleIds)
    {
        if (in_array($moduleId, $serviceModuleIds)) {
            return true;
        }
        return false;
    }

    /**
     * @return array
     */
    public function priceFrequencies()
    {
        return [
            'setup' => 'Setup',
            'one-off' => 'One off',
            'monthly' => 'Monthly',
            'annually' => 'Annually',
            'lifetime' => 'Lifetime',
        ];
    }

    /**
     * @return mixed
     */
    public function getModuleCategories()
    {
        return ModuleCategory::where('status', 1)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param $moduleId
     * @return mixed
     */
    public function getModule($moduleId)
    {
        return Module::where('id', $moduleId)
            ->with('prices')
            ->first();
    }

    /**
     * @param $categoryId
     * @return mixed
     */
    public function getCategory($categoryId)
    {
        return ModuleCategory::where('id', $categoryId)
            ->where('status', 1)
            ->first();
    }

    /**
     * @param $categoryId
     * @return mixed
     */
    public function getModulesInCategory($categoryId)
    {
        return ModuleCategory::find($categoryId)->modules;
    }

    /**
     * @param $moduleId
     * @return mixed
     */
    public function getModuleWithCategories($moduleId)
    {
        return Module::where('id', $moduleId)
            ->with('categories')
            ->orderBy('name')
            ->first();
    }

    /**
     * @return mixed
     */
    public function getTermsAndConditions()
    {
        return TermsAndConditions::orderBy('name')
            ->get();
    }

    /**
     * @param $moduleId
     * @return mixed
     */
    public function getModuleTermsAndConditions($moduleId)
    {
        return Module::find($moduleId)->termsAndConditions;
    }

    /**
     * @param $moduleId
     * @param $contractPeriod
     * @return string
     */
    public function getModuleRenewalDate($moduleId, $contractPeriod) {
        return 'TODO';
    }

    /**
     * Get the module price_plan_ids
     * (this type (free|premium|enterprise) already accounted for);
     * the future price_plan_ids ones are ignored by default
     *
     * @param $moduleId
     * @param $servicePricePlanId
     * @param bool $showFuturePlans
     * @return mixed
     */
    public function getModulePricePlanIds($moduleId, $servicePricePlanId, $showFuturePlans = false)
    {

        $modulePricePlanIds = ModulePrice::where('module_id', $moduleId);

        if (!$showFuturePlans) {
            $modulePricePlanIds = $modulePricePlanIds->where('price_plan_id', '<=', $servicePricePlanId);
        }

        $modulePricePlanIds = $modulePricePlanIds->distinct()->select('price_plan_id')
            ->pluck('price_plan_id');

        return $modulePricePlanIds;

    }

    /**
     * Work out the module price plan id to use
     * The id can't be in the future
     * Ideally it would match the current service price plan
     *  Else use the previous one
     * Every module should start with price plan 1 anyway. (Else how would grandfathered accounts order a new
     * (subsequent) module). So we always work back. Never any need to work forward
     *
     * @param $moduleId
     * @param $servicePricePlanId
     * @return mixed
     */
    public function getModulePricePlanId($moduleId, $servicePricePlanId)
    {
        $modulePricePlanIds = $this->getModulePricePlanIds($moduleId, $servicePricePlanId);

        // looks like this will always be the correct value!
        return $modulePricePlanIds->last();



        /*
        if ($modulePricePlanIds->last() === $servicePricePlanId) {
            // perfect, this module & type has a price plan for the service in use
            dd('match');
        }

        // there isn't an explicit price of this module for this service price plan.
        dd($modulePricePlanIds->last(), 'no match');
        dd($modulePricePlanIds, $servicePricePlanId);
        */

    }

    /**
     * Get the prices for specified module and price plan
     *
     * @param $moduleId
     * @param $modulePricePlanId
     * @return mixed
     */
    public function getModulePrices($moduleId, $modulePricePlanId)
    {
        return ModulePrice::where('module_id', $moduleId)
            ->where('price_plan_id', $modulePricePlanId)
            ->get();
    }

    /**
     * @param $type
     * @param $moduleId
     * @param $modulePricePlanId
     * @return array|bool
     */
    public function getModuleFrequencyPrice($type, $moduleId, $modulePricePlanId) {
        $prices = $this->getModulePrices($moduleId, $modulePricePlanId);
        foreach ($prices as $price) {
            if ($type === $price->price_frequency) {
                return $this->makeVatPrices($price->price);
            }
        }
        return false;
    }

    /**
     * @param $moduleId
     * @param $modulePricePlanId
     * @return bool
     */
    public function hasSetupFee($moduleId, $modulePricePlanId) {
        $setupFee = $this->getModuleFrequencyPrice('setup', $moduleId, $modulePricePlanId);
        return $setupFee && $setupFee > 0 ?: false;
    }

    /**
     * @param $serviceId
     * @param $moduleId
     * @return mixed
     */
    public function serviceModules($serviceId, $moduleId) {
        return Service::where('id', $serviceId)->with(['modules' => function ($query) use($moduleId) {
            $query->where('module_id', $moduleId);
        }])->first();
    }


    /**
     * Does the Service had the Module
     *
     * @param $serviceId
     * @param $moduleId
     * @return bool
     */
    public function serviceHasModule($serviceId, $moduleId) {
        $modules = $this->serviceModules($serviceId, $moduleId)->modules;
        return $modules && $modules->count() > 0 ?: false;
    }

    /**
     * Can the module be instantly activated?
     * If the owner is ordering, they can accept there and then (instant)
     * The owner must accept extra T&Cs. If a staff member is ordering, the order is notified (not instant)
     *
     * @param $isOwner
     * @param $hasTermsAndConditions
     * @return bool
     */
    public function hasInstantActivation($isOwner, $hasTermsAndConditions) {

        if ($hasTermsAndConditions) {
            if ($isOwner) {
                // owner approves, ok
                return true;
            }
            // t&cs, not owner, not instant
            return false;
        }
        // no t&cs, OK
        return true;
    }

    /**
     * Notify the owner that the T&Cs need accepting
     * If the owner is order, he doesn't need to be notified (false)
     * If a staff member is ordering, the owner needs to be notified (true)
     *
     * @param $isOwner
     * @param $hasTermsAndConditions
     * @return bool
     */
    public function notifyOwnerTCs($isOwner, $hasTermsAndConditions) {

        if ($hasTermsAndConditions) {
            if ($isOwner) {
                return false;
            }
            return true;
        }
        return false;

    }


    /**
     * If the owner is ordering, add the terms to the order form at this stage (true)
     * If a staff member is ordering, don't display the terms, and allow the order to continue
     *
     * @param $isOwner
     * @param $hasTermsAndConditions
     * @return bool
     */
    public function displayTermsAcceptance($isOwner, $hasTermsAndConditions) {
        return $this->notifyOwnerTCs($isOwner, $hasTermsAndConditions) ?: false;
    }


    /**
     * Does the module have any Terms and Conditions?
     *
     * @param $moduleId
     * @return bool
     */
    public function hasTermsAndConditions($moduleId) {
        $tacs = Module::find($moduleId)->termsAndConditions;
        return $tacs && $tacs->count() > 0 ?: false;
    }

    /**
     * @return Carbon
     */
    public function nextPaymentScheduleDate() {
        return new Carbon('first day of next month');
    }

    /**
     * @return Carbon
     */
    public function monthModuleContractEndDate() {
        return new Carbon('last day of next month');
    }

    /**
     * @return Carbon
     */
    public function annualModuleContractEndDate() {
        return new Carbon('last day of next month next year');
    }


    /**
     * An Owner will have already accepted the Terms.
     * A Staff member can't accept the Terms.
     *
     * If a staff member orders a module but the owner fails to cancel before the
     * cooling off period (21) days, (2 notification emails and notification on dashboard)
     * the module is implicitly added.
     * However, this mustn't happen if T&Cs need to be accepted by the owner.
     *
     * @param $isOwner
     * @param $hasTermsAndConditions
     * @return bool
     */
    public function disableImplicitActivation($isOwner, $hasTermsAndConditions) {
        return !$isOwner && $hasTermsAndConditions ?: false;
    }

    /**
     * Associate a module with a service
     *
     * @param $serviceId
     * @param array $data
     */
//    public function addModuleToService($serviceId, array $data)
//    {
//        // this doesn't return the results
//        $service = Service::find($serviceId);
//        $service->modules()->attach($data['module_id'], $data);

//dd($data['service_id'] = $serviceId, $data);
//        $moduleService = new ModuleService;
//        return $moduleService->create($data);
//    }

    /**
     * @param $paymentScheduleId
     * @param $moduleService
     * @param array|null $data
     * @return PaymentScheduleItem
     */
    private function paymentLogAddRow($paymentScheduleId, $moduleService, array $data=null) {

        $paymentScheduleItem = new PaymentScheduleItem;

        // add the data array
        foreach($data as $key => $val) {
            $paymentScheduleItem[$key] = $val;
        }
        $paymentScheduleItem->payment_schedule_id = $paymentScheduleId;
        $paymentScheduleItem->module_service_id = $moduleService->id;

        $paymentScheduleItem->price_net = $paymentScheduleItem->prices['net'];
        $paymentScheduleItem->price_vat = $paymentScheduleItem->prices['vat'];
        $paymentScheduleItem->price_gross = $paymentScheduleItem->prices['gross'];

        // unset any arrays. Overloading was a problem so this is the fix for now
        unset($paymentScheduleItem->prices);

        $paymentScheduleItem->save();

        return $paymentScheduleItem;

    }

    /**
     * @param $paymentScheduleId
     * @param $moduleService
     * @param $moduleName
     * @return PaymentScheduleItem
     */
    public function paymentLogAddSetupFee($paymentScheduleId, $moduleService, $moduleName) {

        $data['prices'] = $this->getModuleFrequencyPrice('setup', $moduleService->module_id, $moduleService->price_plan_id);
        $data['recurring'] = 0;
        $data['invoice_row_description'] = $moduleName . ' | Setup fee (one off charge)';

        $paymentScheduleItem = $this->paymentLogAddRow($paymentScheduleId, $moduleService, $data);

        return $paymentScheduleItem;
    }

    /**
     * @param $paymentScheduleId
     * @param $moduleService
     * @param $moduleName
     * @return PaymentScheduleItem
     */
    public function paymentLogAddOneOffFee($paymentScheduleId, $moduleService, $moduleName) {

        $data['prices'] = $this->getModuleFrequencyPrice('one-off', $moduleService->module_id, $moduleService->price_plan_id);
        $data['recurring'] = 0;
        $data['invoice_row_description'] = $moduleName . ' | One off payment';

        $paymentScheduleItem = $this->paymentLogAddRow($paymentScheduleId, $moduleService, $data);
        return $paymentScheduleItem;
    }

    /**
     * @param $paymentScheduleId
     * @param $moduleService
     * @param $moduleName
     * @return PaymentScheduleItem
     */
    public function paymentLogAddLifetimeFee($paymentScheduleId, $moduleService, $moduleName) {

        $data['prices'] = $this->getModuleFrequencyPrice('lifetime', $moduleService->module_id, $moduleService->price_plan_id);
        $data['recurring'] = 0;
        $data['invoice_row_description'] = $moduleName . ' | One off payment';

        $paymentScheduleItem = $this->paymentLogAddRow($paymentScheduleId, $moduleService, $data);
        return $paymentScheduleItem;
    }

    /**
     * @param $netPrice
     * @return array
     */
    private function getMonthlyProRataPrices($netPrice) {

        $dtNow = new Carbon('now');
        $dtEnd = new Carbon('last day of this month');
        $remaining = $dtNow->diffInDays($dtEnd);

        $pricePerDay = $netPrice / 30;
        $priceProRata = $pricePerDay * $remaining;

        return $this->makeVatPrices($priceProRata);

    }

    /**
     * @param $netPrice
     * @return array
     */
    private function getAnnualProRataPrices($netPrice) {

        $dtNow = new Carbon('now');
        $dtEnd = new Carbon('last day of this month');
        $remaining = $dtNow->diffInDays($dtEnd);

        $pricePerDay = $netPrice / 365;
        $priceProRata = $pricePerDay * $remaining;

        return $this->makeVatPrices($priceProRata);

    }

    /**
     * Annual contracts are billed monthly
     *
     * @param $netPrice
     * @return array
     */
    public function getMonthlyAnnualProRataPrices($netPrice) {
        return $this->makeVatPrices($netPrice/12);
    }

    /**
     * @param $paymentScheduleId
     * @param $moduleService
     * @param $moduleName
     * @return PaymentScheduleItem
     */
    public function paymentLogAddCurrentMonthProRataFee($paymentScheduleId, $moduleService, $moduleName) {

        $data['prices'] = $this->getModuleFrequencyPrice('monthly', $moduleService->module_id, $moduleService->price_plan_id);
        $data['pro_rata'] = 1;
        $data['recurring'] = 0;
        $data['invoice_row_description'] = $moduleName . ' | First month pro rata payment (one off charge)';

        // we have the prices for 30 days. Adjust for remaining days in month (pro rata)
        $data['prices'] = $this->getMonthlyProRataPrices($data['prices']['net']);

        $paymentScheduleItem = $this->paymentLogAddRow($paymentScheduleId, $moduleService, $data);
        return $paymentScheduleItem;
    }

    /**
     * @param $paymentScheduleId
     * @param $moduleService
     * @param $moduleName
     * @return PaymentScheduleItem
     */
    public function paymentLogAddAnnualProRataFee($paymentScheduleId, $moduleService, $moduleName) {

        $data['prices'] = $this->getModuleFrequencyPrice('annually', $moduleService->module_id, $moduleService->price_plan_id);
        $data['pro_rata'] = 1;
        $data['recurring'] = 0;
        $data['invoice_row_description'] = $moduleName . ' | First month pro rata payment (one off charge)';

        // we have the prices for 1 year. Adjust for remaining days in month (pro rata)
        $data['prices'] = $this->getAnnualProRataPrices($data['prices']['net']);

        $paymentScheduleItem = $this->paymentLogAddRow($paymentScheduleId, $moduleService, $data);
        return $paymentScheduleItem;
    }

    /**
     * @param $paymentScheduleId
     * @param $moduleService
     * @param $moduleName
     * @return PaymentScheduleItem
     */
    public function paymentLogAddMonthlyFee($paymentScheduleId, $moduleService, $moduleName) {

        $data['prices'] = $this->getModuleFrequencyPrice('monthly', $moduleService->module_id, $moduleService->price_plan_id);
        $data['recurring'] = 1;
        $data['invoice_row_description'] = $moduleName . ' | Monthly payment';

        $paymentScheduleItem = $this->paymentLogAddRow($paymentScheduleId, $moduleService, $data);
        return $paymentScheduleItem;
    }

    /**
     * @param $paymentScheduleId
     * @param $moduleService
     * @param $moduleName
     * @return PaymentScheduleItem
     */
    public function paymentLogAddAnnualFee($paymentScheduleId, $moduleService, $moduleName) {

        $data['prices'] = $this->getModuleFrequencyPrice('annually', $moduleService->module_id, $moduleService->price_plan_id);
        $data['recurring'] = 1;
        $data['invoice_row_description'] = $moduleName . ' | Annual contract. Discounted monthly payments';

        // we have the prices for 30 days. Adjust for remaining days in month (pro rata)
        $data['prices'] = $this->getMonthlyAnnualProRataPrices($data['prices']['net']);

// disable terms on the diactivate page

        $paymentScheduleItem = $this->paymentLogAddRow($paymentScheduleId, $moduleService, $data);
        return $paymentScheduleItem;
    }

    // ---------------
    // --------------- B U I L D    P A Y M E N T   L O G
    // ---------------

    /**
     * Run Via Cron on 1st of each month
     *
     * Iterate each module_service where 'active'
     *
     * Iterate each 'pending' and check if they need to be moved
     *
     *
     * @param $modulePrices
     * @param string $type
     * @return string
     *
     */



    // ---------------

    /**
     * @param $modulePrices
     * @param string $type
     * @return string
     */
    public function getViewModulePrices($modulePrices, $type = 'col')
    {
//        $pricePlan = $modulePrices->load('pricePlan')->first();
//        dd($pricePlan->pricePlan->name);

        $str = '<div class="table-responsive"><table class="table table-striped table-bordered table-hover table-condensed">';

        if ($type === 'col') {
            $str .= '<tr><th></th><th>Amount</th><th></th></tr>';
            foreach ($modulePrices as $priceRow) {
                $str .= '<tr><td>' . ucfirst($priceRow->price_frequency) . '</td>
                <td class="text-right">' . $priceRow->displayPrice() . '</td>
                <td class="text-right">';

                if ($priceRow->price_frequency !== 'setup') {
                    $str .= '<a class="btn btn-sm btn-success btn-block" href="' . route('moduleActivate', [$priceRow->module_id, $priceRow->price_frequency]) . '">Activate <strong>' . ucfirst($priceRow->price_frequency) . '</strong></a>';
                }

                $str .= '</td>
                </tr>';
            }
        } else {
            $str .= '<tr>';
            foreach ($modulePrices as $priceRow) {
                $str .= '<td>' . ucfirst($priceRow->price_frequency) . '</td><td class="text-right">Activate: ' . $priceRow->displayPrice() . '</td>';
            }
            $str .= '</tr>';
        }

        $str .= '</table></div>';

        return $str;

    }

    /**
     * @param $moduleService
     * @param $modulePrices
     * @return string
     */
    public function viewDeactivateModulePage($moduleService, $modulePrices) {

        $renewalDate = $this->getModuleRenewalDate($moduleService->moduleId, $moduleService->contract_period);

//        dd($moduleService->load('pricePlan'));

        $deactivate = '<h3>Deactivate Module</h3>
<div class="table-responsive">
    <table class="table table-striped table-bordered table-hover table-condensed">
        <tr>
            <td>Order Date</td>
            <td>' . $moduleService->order_date . '</td>
        </tr>
        <tr>
            <td>Start Date</td>
            <td>' . $moduleService->start_date . '</td>
        </tr>        
        <tr>
            <td>Renewal Date</td>
            <td>' . $renewalDate . '</td>
        </tr>
        <tr>
            <td>Contract Period</td>
            <td>' . ucfirst($moduleService->contract_period) . '</td>
        </tr>
        <tr>
            <td>Status</td>
            <td>' . ucfirst($moduleService->status) . '</td>
        </tr>                      
        <tr>
            <td>Price</td>
            <td>&pound;TBC per ' . $moduleService->contract_period . '</td>
        </tr>
    </table>
</div>';

        if(true) {
            $deactivate .= '<p><a class="btn btn-link" href="' . route('todo') . '">Deactivate Module</a></p>';
        } else {
            $deactivate .= '<p><a class="btn btn-muted" disabled href="' . route('todo') . '">Deactivate Module</a></p>';
        }

        return $deactivate;

    }



}