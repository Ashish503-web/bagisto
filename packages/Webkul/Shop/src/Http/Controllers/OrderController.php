<?php

namespace Webkul\Shop\Http\Controllers;

use Webkul\Core\Models\CoreConfig;
use Webkul\Product\Models\ProductInventory;
use Webkul\Product\Models\ProductOrderedInventory;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;
use PDF;

class OrderController extends Controller
{
    /**
     * OrderrRepository object
     *
     * @var \Webkul\Sales\Repositories\OrderRepository
     */
    protected $orderRepository;

    /**
     * InvoiceRepository object
     *
     * @var \Webkul\Sales\Repositories\InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Order\Repositories\OrderRepository  $orderRepository
     * @param  \Webkul\Order\Repositories\InvoiceRepository  $invoiceRepository
     * @return void
     */
    public function __construct(
        OrderRepository $orderRepository,
        InvoiceRepository $invoiceRepository
    )
    {
        $this->middleware('customer');

        $this->orderRepository = $orderRepository;

        $this->invoiceRepository = $invoiceRepository;

        parent::__construct();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
    */
    public function index()
    {
        return view($this->_config['view']);
    }

    /**
     * Show the view for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function view($id)
    {
        $order = $this->orderRepository->findOneWhere([
            'customer_id' => auth()->guard('customer')->user()->id,
            'id'          => $id,
        ]);

        if (! $order) {
            abort(404);
        }

        foreach($order->items as $item)
        {

            $order_product = ProductOrderedInventory::where('product_id', '=', $item->product_id)->first();
            $product = ProductInventory::where('product_id', '=', $item->product_id)->first();


            if($order_product['product_id'] == $product['product_id']){
                $in_stock_product = $product['qty']-$order_product['qty'];
                if($in_stock_product == 0){
                    $in_stock_product = '';
                }

            }
        }

        $reorder = CoreConfig::where('code','=','customer.settings.re-order.re-order')
            ->first();

        return view($this->_config['view'], compact('order','in_stock_product','reorder'));
    }

    /**
     * Print and download the for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function print($id)
    {
        $invoice = $this->invoiceRepository->findOrFail($id);

        $pdf = PDF::loadView('shop::customers.account.orders.pdf', compact('invoice'))->setPaper('a4');

        return $pdf->download('invoice-' . $invoice->created_at->format('d-m-Y') . '.pdf');
    }

    /**
     * Cancel action for the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function cancel($id)
    {
        $result = $this->orderRepository->cancel($id);

        if ($result) {
            session()->flash('success', trans('admin::app.response.cancel-success', ['name' => 'Order']));
        } else {
            session()->flash('error', trans('admin::app.response.cancel-error', ['name' => 'Order']));
        }

        return redirect()->back();
    }
}