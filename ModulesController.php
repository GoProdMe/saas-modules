<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Repositories\ModuleRepository;
use App\Repositories\PricingRepository;
use App\Repositories\ServiceRepository;

use App\Http\Requests;

use App\ModuleCategory;
use App\PaymentSchedule;
use App\PaymentScheduleItem;
use App\Module;
use App\ModuleService;
use App\Price;

class ModulesController extends Controller
{
    public function __construct(
        ModuleRepository $module,
        PricingRepository $pricing,
        ServiceRepository $service
    )
    {
        $this->module = $module;
        $this->pricing = $pricing;
        $this->service = $service;
    }

    public function index($categoryId = null)
    {
        $modules = Module::orderBy('name_id')->paginate(20);
        $moduleCategories = $this->module->getModuleCategories();

        $serviceModuleIds = $this->module->getServiceModuleIds(serviceId());

        if (!$categoryId) {
            // show the module landing page
            // WARNING: if adding vars remember this method has 2 views
            return view('modules.index', compact('modules', 'moduleCategories', 'serviceModuleIds'));
        }

        $modules = $this->module->getModulesInCategory($categoryId);
        $currentCategory = $this->module->getCategory($categoryId);

        // WARNING: if adding vars remember this method has 2 views
        return view('modules.category', compact('modules', 'moduleCategories', 'currentCategory', 'serviceModuleIds'));

    }

    public function show($moduleId)
    {

//        $notifyOwnerTCs = $this->module->notifyOwnerTCs(isOwner(), $hasTermsAndConditions);
//        $displayTermsAcceptance = $this->module->displayTermsAcceptance(isOwner(), $hasTermsAndConditions);

        $isOwner = isOwner();

        $hasTermsAndConditions = $this->module->hasTermsAndConditions($moduleId);
        $hasInstantActivation = $this->module->hasInstantActivation(isOwner(), $hasTermsAndConditions);

        $moduleCategories = $this->module->getModuleCategories();

        $module = $this->module->getModule($moduleId);

        $serviceModuleIds = $this->module->getServiceModuleIds(serviceId());
        $hasModule = $this->module->hasModule($moduleId, $serviceModuleIds->toArray());

        // ------------------------------
        // CONSOLIDATE IN REPO? BGN work out what prices to display for the module

        $deactivate = $viewPriceGrid = null;

        $servicePricePlanId = $this->service->getCurrentPricePlanId();
        $modulePricePlanId = $this->module->getModulePricePlanId($moduleId, $servicePricePlanId);
        $modulePrices = $this->module->getModulePrices($moduleId, $modulePricePlanId);

        if (!$hasModule) {

// this possibly isn't being used  as intended. Should it be being passed into a
// function below, or is it superfluous. The code seems to work ok
            $currentSystemPricePlanId = Price::where('active', '1')->pluck('id')->first();

            $modulePricePlanId = $this->module->getModulePricePlanId($moduleId, $servicePricePlanId);
            $modulePrices = $this->module->getModulePrices($moduleId, $modulePricePlanId);
            $viewPriceGrid = $this->module->getViewModulePrices($modulePrices, 'col');

        } else {
            $moduleService = $this->module->getServiceModule(serviceId(), $moduleId);
            $deactivate = $this->module->viewDeactivateModulePage($moduleService, $modulePrices);
        }

        // CONSOLIDATE? END work out what prices to display for the module
        // ------------------------------

        $terms = $this->module->getModuleTermsAndConditions($moduleId);

        return view('modules.order', compact(
            'module', 'moduleCategories', 'viewPriceGrid', 'deactivate', 'hasModule', 'terms',
            'hasInstantActivation', 'hasTermsAndConditions', 'isOwner'
        ));
    }

    /**
     * Activate the module - IE associate it with the service, provided it's not already associated
     *
     * If an Owner is ordering and there are Terms & Conditions, then they must accept the terms at
     * the point of ordering. The module will be instantly actively on acceptance.
     *
     * If a Staff Member is ordering, the module status is pending for up to 21 days. Owner(s) are
     * notified to acknowledge the terms.
     *
     * If No T&Cs and Owner or Staff, module is instantly active from today.
     *
     * TBC - THE TERMS WOULD ALREADY HAVE BEEN ACCEPTED IN ORDER TO HAVE GOT TO THIS STAGE
     *
     * The ContractDates would be rerun once the module is accepted when Terms exist.
     *
     * @param $moduleId
     * @param $contractPeriod
     * @return \Illuminate\Http\RedirectResponse
     */
    public function activate($moduleId, $contractPeriod)
    {
        if ( $this->module->serviceHasModule(serviceId(), $moduleId) ) {
            \Session::flash('message_error', 'This module is already installed');
            return redirect()->route('modules');
        }

        // get helper vars
        $isOwner = isOwner();

        $servicePricePlanId = $this->service->getCurrentPricePlanId();
        $modulePricePlanId = $this->module->getModulePricePlanId($moduleId, $servicePricePlanId);

        $moduleName = $this->module->getModule($moduleId)->name;

        $hasTermsAndConditions = $this->module->hasTermsAndConditions($moduleId);
        $hasSetupFee = $this->module->hasSetupFee($moduleId, $modulePricePlanId);

        $disableImplicitActivation = $this->module->disableImplicitActivation(isOwner(), $hasTermsAndConditions);

        // ---------------------------------

        $contractData = $this->module->getContractData(isOwner(), $hasTermsAndConditions);

        $moduleService = new ModuleService;

        $moduleService->service_id = serviceId();
        $moduleService->module_id = $moduleId;

        $moduleService->price_plan_id = $modulePricePlanId; // the current module price_plan which the user is signing up to
        $moduleService->order_date = date('Y-m-d H:i:s');
        $moduleService->ordered_by_user = serviceUserId();

        $moduleService->contract_period = $contractPeriod;

        foreach($contractData as $key => $val) {
            $moduleService[$key] = $val;
        }

        $moduleService->save();

        // ---------------------------------

        // Add to PaymentSchedule unless: 'User is not Owner and there are T&Cs to accept'
        // in which case the Acceptance process with deal with scheduling payment
        if (!$disableImplicitActivation) {

            // create or find the payment schedule for this service
            $nextPaymentScheduleDate = $this->module->nextPaymentScheduleDate();
            $paymentSchedule = PaymentSchedule::firstOrCreate([
                'service_id' => serviceId(),
                'date' => $nextPaymentScheduleDate->format('Y-m-d')
            ]);

            // add the setup fee, remaining 'monthly pro rata and monthly', 'annual', 'lifetime/one-off' items
            if ($hasSetupFee) {
                $this->module->paymentLogAddSetupFee($paymentSchedule->id, $moduleService, $moduleName);
            }

            if ($contractPeriod === 'monthly') {
                $this->module->paymentLogAddCurrentMonthProRataFee($paymentSchedule->id, $moduleService, $moduleName);
                $this->module->paymentLogAddMonthlyFee($paymentSchedule->id, $moduleService, $moduleName);

            } else if ($contractPeriod === 'annually') {
                $this->module->paymentLogAddAnnualProRataFee($paymentSchedule->id, $moduleService, $moduleName);
                $this->module->paymentLogAddAnnualFee($paymentSchedule->id, $moduleService, $moduleName);

            } else if ($contractPeriod === 'lifetime') {
                $this->module->paymentLogAddLifetimeFee($paymentSchedule->id, $moduleService, $moduleName);

            } else if ($contractPeriod === 'one-off') {
                $this->module->paymentLogAddOneOffFee($paymentSchedule->id, $moduleService, $moduleName);
            }

        } else {

            // TODO send the email to the owner
            // give option to change the payment frequency

        } // end if adding to payment log

        // ------------------

        $moduleCategories = $this->module->getModuleCategories();
        $module = $this->module->getModule($moduleId);

        return view('modules.activation-confirmation', compact('moduleCategories','module','hasTermsAndConditions','isOwner'));


    } // end activate module


    
}
