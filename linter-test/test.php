<?php

namespace App\Http\Controllers\Lab;

use App\Helpers\AppHelper;
use App\Helpers\Sentinel;
use App\Http\Requests\Order\OrderConfigsRequest;
use App\Http\Requests\Order\OrderStoreRequest;
use App\Http\Requests\Order\UploadImageRequest;
use App\Http\Requests\Order\OrderEditRequest;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderFile;
use App\Models\OrderSetItem;
use App\Models\OrderSettings;
use App\Models\OrderStatusChangelog;
use App\Models\Shop;
use App\Modules\Frontend\Models\Users;
use App\Services\OrderFileService;
use App\Services\OrderService;
use Barryvdh\Debugbar\Facade;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DB;

class OrdersController extends LabController
{
    protected $customer;
    
    protected $orderSetItem;
    
    protected $order;
    



    protected $orderFile;
    
    protected $orderFileService;
    
    protected $orderSettings;
    
    protected $users;
    
    protected $conversation;

    public function __construct(
        Customer $customer,
        OrderSetItem $orderSetItem,
        Order $order,
        OrderFile $orderFile,
        OrderFileService $orderFileService,
        OrderService $orderService,
        OrderSettings $orderSettings,
        Users $users,
        Conversation $conversation
    ) {
        parent::__construct();

        $this->customer = $customer;
        $this->orderSetItem = $orderSetItem;
        $this->order = $order;
        $this->orderFile = $orderFile;
        $this->orderFileService = $orderFileService;
        $this->orderService = $orderService;
        $this->orderSettings = $orderSettings;
        $this->users = $users;
        $this->conversation = $conversation;
    }

    public function search(Request $request)
    {
        $allRequest = AppHelper::filterRequest($request);
        $fieldSort = DB::raw('IF(order_shipping_date = \'0000-00-00 00:00:00\', 4, order_shipping_date)');
        $orderBy = 'ASC';

        if (count($allRequest['sort'])) {
            $fieldSort = key($allRequest['sort']);
            $orderBy = $allRequest['sort'][$fieldSort];
        }
        $dataSearch = array();
        $filedSearch = array(
            'full_name',
            'lab_worker',
            'order_date_from',
            'order_date_to',
            'ship_date_from',
            'ship_date_to',
            'order_status',
        );
        foreach ($filedSearch as $key) {
            if (isset($allRequest['filter'][$key])) {
                $dataSearch[$key] = $allRequest['filter'][$key];
            }
        }
        $orders = $this->order->getListOrderByStatus($dataSearch, $fieldSort, $orderBy);
        $orders->appends($request->except('page'));
        $orderStatus = config('constant.order_status');
        $tableHeader = [
            'order_id' => ['name' => __('lab.Order ID'), 'style' => ''],
            'created_at' => ['name' => __('lab.Order date'), 'style' => ''],
            'order_setname' => ['name' => __('lab.Set name'), 'style' => ''],
            'order_number_setname' => ['name' => __('lab.Number of times title'), 'style' => ''],
            'full_name' => ['name' => __('lab.The patient name'), 'style' => ''],
            'order_shipping_date' => ['name' => __('lab.Shipping date'), 'style' => ''],
            'order_status' => ['name' => __('lab.Status'), 'style' => ''],
            'order_memo' => ['name' => __('lab.Memo'), 'style' => ''],
        ];
        $dspDentistry = Sentinel::inRole(ROLE_ADMIN) || Sentinel::inRole(ROLE_LAP_MANAGER);
        if ($dspDentistry) {
            $tableHeader ['shop_name'] = ['name' => __('lab.Dentistry'), 'style' => ''];
            $tableHeader ['worker_name'] = ['name' => __('lab.Lab worker'), 'style' => ''];
        }

        $labWorkers = $this->users->getAllLabWorker(['id', 'full_name']);

        $title = __('lab.Order / patient search');
        return view(
            'lab.orders.shop.search',
            compact(
                'orderStatus',
                'orders',
                'dspDentistry',
                'tableHeader',
                'title',
                'labWorkers'
            )
        );
    }

    public function listOrders(Request $request, $status)
    {
        $allRequest = AppHelper::filterRequest($request);
        $fieldSort = DB::raw('IF(order_shipping_date = \'0000-00-00 00:00:00\', 4, order_shipping_date)');
        $orderBy = 'ASC';

        if (count($allRequest['sort'])) {
            $fieldSort = key($allRequest['sort']);
            $orderBy = $allRequest['sort'][$fieldSort];
        }
        $orderStatusStr = config('constant.order_status_str');
        $statusStr = $status;
        $status = isset($orderStatusStr[$status]) ? $orderStatusStr[$status] : ORDER_CONFIRM;
        $labWorkerSearch = !empty($allRequest['filter']['lab_worker']) ? ['lab_worker' => $allRequest['filter']['lab_worker']] : [];
        $orders = $this->order->getListOrderByStatus(['order_status' => $status] + $labWorkerSearch, $fieldSort, $orderBy);
        $orders->appends($request->except('page'));
        $orderStatus = config('constant.order_status');
        $dspDentistry = Sentinel::inRole(ROLE_ADMIN) || Sentinel::inRole(ROLE_LAP_MANAGER);
        $authChangeStatus = Sentinel::inRole(ROLE_ADMIN) || Sentinel::inRole(ROLE_LAP_MANAGER) || Sentinel::inRole(ROLE_LAB_WORKER);
        $dsp = $authChangeStatus && in_array($statusStr, [ORDER_CONFIRM_STR, ORDER_SETUP_DESIGN_STR, ORDER_PRE_SHIP_STR]);
        $labWorkers = $this->users->getAllLabWorker(['id', 'full_name']);

        $tableHeader = [
            'order_id' => ['name' => __('lab.Order ID'), 'style' => ''],
            'created_at' => ['name' => __('lab.Order date'), 'style' => ''],
            'order_setname' => ['name' => __('lab.Set name'), 'style' => ''],
            'order_number_setname' => ['name' => __('lab.Number of times title'), 'style' => ''],
            'full_name' => ['name' => __('lab.The patient name'), 'style' => ''],
            'order_shipping_date' => ['name' => __('lab.Shipping date'), 'style' => ''],
            'order_status' => ['name' => __('lab.Status'), 'style' => ''],
            'order_memo' => ['name' => __('lab.Memo'), 'style' => ''],
        ];
        if ($dspDentistry) {
            $tableHeader ['shop_name'] = ['name' => __('lab.Dentistry'), 'style' => ''];
            $tableHeader ['worker_name'] = ['name' => __('lab.Lab worker'), 'style' => ''];
        }
        $txtBtn = '';
        $title = __('lab.New orders');
        switch ($status) {
            case ORDER_CONFIRM:
                $title = __('lab.Confirming orders');
                $txtBtn = __('lab.Bulk Receiving');
                break;
            case ORDER_SETUP_DESIGN:
                $title = __('lab.Setup design orders');
                $txtBtn = __('lab.Prepare to ship');
                break;
            case ORDER_PRE_SHIP:
                $title = __('lab.Prepare to ship orders');
                $txtBtn = __('lab.Bulk Shipping');
                break;
            case ORDER_SHIP:
                $title = __('lab.Shipped orders');
                break;
            case ORDER_CANCEL:
                $title = __('lab.Cancel orders');
                break;
        }
        return view(
            'lab.orders.shop.list',
            compact(
                'status',
                'orders',
                'orderStatus',
                'statusStr',
                'dsp',
                'dspDentistry',
                'tableHeader',
                'title',
                'labWorkers',
                'txtBtn'
            )
        );
    }

    public function updateStatus(Request $request)
    {
        if (!$request->ajax()) {
            abort(404);
        }

        $rs = $this->order->bulkUpdate($request->get('ids', []));
        return response()->json(
            [
            'result' => $rs,
            'url_redirect' => url()->previous()
            ]
        );
    }

    public function create()
    {
        $complaint = config('constant.complaint');
        $deliveryTime = $this->orderSettings->getValueByKey(CONF_DELIVERY_TIME);
        $shipDate = Carbon::now()->addDay($deliveryTime)->format('Y/m/d');
        $dspShipDate = Sentinel::inRole(ROLE_LAB_WORKER) || Sentinel::inRole(ROLE_LAP_MANAGER) || Sentinel::inRole(ROLE_ADMIN);
        $setItems = $this->orderSetItem->getAll();
        $shop = $this->shop;
        $isShop = $this->isShop();
        $clinic = Shop::getAllShopsList();
        $dspMoney = Sentinel::inRole(ROLE_ADMIN) || Sentinel::inRole(ROLE_LAP_MANAGER);
        $labWorkers = $this->users->getAllLabWorker(['id', 'full_name']);
        $title = __('lab.Create New Title');
        return view(
            'lab.orders.shop.create',
            compact(
                'complaint',
                'setItems',
                'clinic',
                'deliveryTime',
                'shipDate',
                'dspShipDate',
                'dspMoney',
                'title',
                'shop',
                'isShop',
                'labWorkers'
            )
        );
    }

    public function uploadImage(UploadImageRequest $request)
    {
        if (!$request->ajax()) {
            abort(404);
        }
        $files = $request->file('file');
        $fileNames = $request->get('image_name');
        $fileProps = $request->get('image_prop');
        $orderID = $request->get('order_id');
        $rs = $this->orderFile->store($orderID, $files, $fileNames, $fileProps);

        return response()->json(
            [
            'result' => $rs
            ]
        );
    }

    public function store(OrderStoreRequest $request)
    {
        if (!$request->ajax()) {
            abort(404);
        }
        $rs = $this->order->storeOrder($request->getDataOrder(), $request->getDataCustomer());
        return response()->json(
            [
            'result' => $rs,
            'url_redirect' => route('orders_list', ORDER_CONFIRM_STR)
            ]
        );
    }

    public function searchPatient(Request $request)
    {
        if (!$request->ajax()) {
            abort(404);
        }
        $dspDentistry = Sentinel::inRole(ROLE_ADMIN) || Sentinel::inRole(ROLE_LAP_MANAGER);
        $customerStatus = Sentinel::inRole(ROLE_SHOP_ADMIN) ? [CUSTOMER_OUTOFDATE, CUSTOMER_VISITED] : null;
        $customers = $this->customer->searchCustomerForOrder($request->get('id', 0), $request->get('name', ''), null, $customerStatus);
        $oldVisit = [];
        foreach ($customers as $customer) {
            $oldVisit[$customer->id] = $this->getOldOrderOfCustomer($customer->order);
        }
        $html = view(
            'lab.orders.shop.searchCustomer',
            compact(
                'customers',
                'dspDentistry',
                'oldVisit'
            )
        )->render();
        return response()->json(
            [
            'html' => $html
            ]
        );
    }

    public function historyPatient(Request $request, $id = 0)
    {
        if (!$request->ajax()) {
            abort(404);
        }

        $orderHistory = $this->order->getListOrderHistoryForUser($id);
        $orderStatus = config('constant.order_status');
        $html = view(
            'lab.orders.shop.orderHistory',
            compact(
                'orderHistory',
                'orderStatus'
            )
        )->render();
        return response()->json(
            [
            'html' => $html
            ]
        );
    }

    public function detail($id)
    {
        $orderDetail = $this->order->where('id', $id);

        if ($this->isShop()) {
            $shopId = $this->shop->id;
            $orderDetail->where('shop_id', $shopId);
        }

        $orderDetail = $orderDetail->first();

        if (!$orderDetail) {
            abort(404);
        }

        $conversation = $orderDetail->conversation;

        if (!$conversation) {
            $conversation = $this->conversation;
            $conversation->room = chatRoom($orderDetail);
            $conversation->order_id = $id;
            $conversation->patient_id = $orderDetail->patient_id;
            $conversation->conversation_name = $orderDetail->order_name;

            $conversation->save();
            $messages = [];
        } else {
            $messages = $conversation->messages;
        }

        $orderFiles = $this->orderFile->getImageByOrderId($orderDetail->id);
        $txtBtn = '';
        $orderStatus = $orderDetail->order_status;
        $title = "注文ID " . $orderDetail->order_id;
        switch ($orderStatus) {
            case ORDER_CONFIRM:
                $txtBtn = __('lab.Bulk Receiving');
                break;
            case ORDER_SETUP_DESIGN:
                $txtBtn = __('lab.Prepare to ship');
                break;
            case ORDER_PRE_SHIP:
                $txtBtn = __('lab.Bulk Shipping');
                break;
        }
        $dspDentistry = Sentinel::inRole(ROLE_ADMIN) || Sentinel::inRole(ROLE_LAP_MANAGER);
        $dsp = $dspDentistry && in_array($orderStatus, [ORDER_CONFIRM, ORDER_SETUP_DESIGN, ORDER_PRE_SHIP]);

        $symptom = config('constant.complaint');
        $orderHistory = $orderDetail->order_histories;
        $labWorkers = $this->users->getAllLabWorker(['id', 'full_name']);
        $oldVisit = $this->getOldOrderOfCustomer($orderDetail->customer->order);

        if ($this->isShop()) {
            return view('lab.orders.shop.detail', compact('orderDetail', 'orderFiles', 'txtBtn', 'dsp', 'symptom', 'title', 'messages', 'conversation', 'orderHistory', 'oldVisit'));
        }

        $statusList = config('constant.order_status_str');

        return view('lab.orders.lab.detail', compact('orderDetail', 'orderFiles', 'txtBtn', 'dsp', 'symptom', 'title', 'labWorkers', 'messages', 'conversation', 'orderHistory', 'statusList', 'oldVisit'));
    }

    public function saveOrderDetail(OrderEditRequest $request, $id)
    {
        if (!$this->order->storeOrderInfo($id, $request->getDataOrder())) {
            abort(404);
        }

        if (!$request->ajax()) {
            return back();
        }

        return 'success';
    }

    public function editImage(Request $request)
    {
        if (!$request->ajax()) {
            abort(404);
        }

        $this->orderFileService->editImage($request->imageData);
        return 'success';
    }

    public function downloadFile($id)
    {
        $file = OrderFile::findOrFail($id);

        if (!$file) {
            abort(404);
        }

        $fileUrl = $file->order_file_url;
        $fileName = $file->order_file_name;

        return response()->download(public_path($fileUrl), $fileName);
    }

    public function configs()
    {
        $deliveryTime = $this->orderSettings->getValueByKey(CONF_DELIVERY_TIME);
        $title = __('lab.Configs');
        return view('lab.orders.configs', compact('deliveryTime', 'title'));
    }

    public function storeConfigs(OrderConfigsRequest $request)
    {
        $rs = $this->orderSettings->store($request->getDataOrderSetting());
        if ($rs) {
            session()->flash('success', __('lab.Save success'));
            return redirect()->route('orders_config_store');
        }
        return redirect()->back()
            ->withInput()
            ->withErrors(__('lab.Save fail'));
    }

    public function detailUpdateStatus($id)
    {
        $rs = $this->order->updateOrderStatus($id);
        if ($rs) {
            session()->flash('success', __('lab.Save success'));
            return redirect()->back();
        }
        return redirect()->back()
            ->withInput()
            ->withErrors(__('lab.Save fail'));
    }

    public function cancelOrder(Request $request, $id)
    {
        if (!$request->ajax()) {
            abort(404);
        }

        $order = $this->order->find($id);
        $order->order_status = ORDER_CANCEL;

        if ($order->save()) {
            OrderStatusChangelog::storeOrderStatusLog($order, $order->order_status);
            return 'success';
        }
    }

    public function exportInvoice(Request $request, $id)
    {
        $order = $this->order->where('id', $id);
        $isShop = $this->isShop();

        $title = __('lab.Kireilign Supplies');
        $title2 = __('lab.Kireilign order confirmation');
        $view = 'lab.orders.lab.orderInvoice';
        $orderDetail = $order->first();
        $data = compact('title', 'title2', 'orderDetail', 'isShop');

        if ($isShop) {
            $shopId = $this->shop->id;
            $orderDetail = $order->where('shop_id', $shopId)->first();

            $title = __('lab.Kireilign order form (customer receipt)');
            $view = 'lab.orders.shop.orderInvoice';
            $data = compact('title', 'orderDetail', 'isShop');
        }


        $pdf = \PDF::setOptions(['defaultFont' => 'ipag', 'isFontSubsettingEnabled' => true])
            ->loadView($view, $data);

        return $pdf->download(Carbon::now()->format('Ymd') . '_' . $orderDetail->order_id . '.pdf');
    }

    public function export(Request $request)
    {
        if (!Sentinel::inRole(ROLE_ADMIN)) {
            abort(404);
        }

        $selectDate = $request->get('select', null);

        if ($selectDate != null) {
            try {
                $selectDate = $selectDate !== 'All' ? Carbon::parse($selectDate) : false;
            } catch (\Exception $e) {
                abort(404);
            }

            return $this->orderService->exportData($selectDate);
        }

        $date = Order::select(DB::raw('YEAR(created_at) AS year, MONTH(created_at) AS month'))->groupby('year', 'month')->take(12)->get()->sortByDesc('month')->toarray();
        return view('lab.orders.shop.export', compact('date'));
    }

    public function calculateCost(Request $request)
    {
        $data = $request->only(
            [
            'order_setname',
            'order_number_setname',
            'order_spe_se_above',
            'order_spe_se_under',
            'order_rme_above',
            'order_rme_under',
            'order_clear_retainer_above',
            'order_clear_retainer_under',
            'order_alignersoft_above',
            'order_alignersoft_under',
            'order_medium_above',
            'order_medium_under',
            'order_hard_above',
            'order_hard_under',
            'order_switch_service',
            'order_last_hard'
            ]
        );
        $data['order_money'] = 0;

        Facade::info($data);
        $order = $this->order->fill($data);
        Facade::info($order);

        return response()->json(
            [
            'order_money' => $order->order_money
            ]
        );
    }

    public function getOldOrderOfCustomer($listOrders)
    {
        if (count($listOrders) < 1) {
            return [];
        }

        $arr = [];
        foreach ($listOrders as $order) {
            if ($order->orderSetItem->type != 0) {
                continue;
            }

            if ($order->order_visit_number == null) {
                $order->order_visit_number = 1;
            }

            for ($i = 0; $i < $order->order_visit_number; $i++) {
                if (!isset($arr[$order->orderSetItem->id])) {
                    $arr[$order->orderSetItem->id] = [];
                }

                if (!in_array($order->order_number_setname + $i, $arr[$order->orderSetItem->id])) {
                    $arr[$order->orderSetItem->id][] = $order->order_number_setname + $i;
                }
            }
        }

        return $arr;
    }
}
