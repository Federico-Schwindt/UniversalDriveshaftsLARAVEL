<?php
$id = DB::table('orders')->insertGetId([
  'order_number' => 'UDS-261018-TEST', 'user_id' => null, 'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'manual',
  'email' => 'test-track@uds.local', 'phone' => '+1 813-524-6491', 'sms_opt_in' => 1,
  'billing_first_name' => 'Taylor', 'billing_last_name' => 'Driver', 'billing_company' => 'Gulf Coast Fleet Service',
  'billing_address_1' => '4210 W Hillsborough Ave', 'billing_address_2' => 'Bay 7', 'billing_city' => 'Tampa', 'billing_state' => 'FL', 'billing_postcode' => '33614', 'billing_country' => 'US',
  'ship_to_different' => 0, 'shipping_method' => 'ups_walleted_ups_ground', 'shipping_label' => 'UPS Ground',
  'subtotal' => 684.00, 'shipping_total' => 42.18, 'discount_total' => 0, 'total' => 726.18,
  'customer_note' => '', 'po_number' => 'PO-88121', 'signature_required' => 1, 'ship_instructions' => 'Leave at the parts counter, dock B.',
  'ip' => '127.0.0.1', 'created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3),
]);
DB::table('order_items')->insert([
  ['order_id'=>$id,'type'=>'custom_build','wp_product_id'=>null,'name'=>'Custom 1350 series steel driveshaft, 54.25" C-to-C','sku'=>'DSB-1350-5425','price'=>549.00,'qty'=>1,'line_total'=>549.00,'meta'=>json_encode(['summary'=>'1350 series · 3.5" x .083 DOM · slip yoke 32 spline · 1350 flange yoke']),'created_at'=>now(),'updated_at'=>now()],
  ['order_id'=>$id,'type'=>'product','wp_product_id'=>null,'name'=>'Spicer 5-178X 1350 series u-joint','sku'=>'5-178X','price'=>67.50,'qty'=>2,'line_total'=>135.00,'meta'=>'{}','created_at'=>now(),'updated_at'=>now()],
]);
$o = DB::table('orders')->find($id);
\App\Support\OrderEvents::record($o, 'placed', 'Order placed', 'UPS Ground', true);
DB::table('order_events')->where('order_id',$id)->update(['created_at'=>now()->subDays(3)]);
echo "created id=$id ", \App\Support\OrderEvents::statusUrl($o), "\n";
