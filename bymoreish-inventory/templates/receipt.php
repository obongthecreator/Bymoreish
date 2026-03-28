<?php
// Standalone receipt template loaded via AJAX or print window
// $order and $order_items passed from the calling context or via GET param order_id
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: 'Courier New', Courier, monospace; font-size: 12px; background: #fff; color: #111; }
  .receipt { max-width: 302px; margin: 0 auto; padding: 12px 10px; }
  .center { text-align: center; }
  .right { text-align: right; }
  .bold { font-weight: bold; }
  .divider { border: none; border-top: 1px dashed #555; margin: 8px 0; }
  .divider-solid { border: none; border-top: 1px solid #111; margin: 8px 0; }
  .logo-name { font-size: 20px; font-weight: bold; letter-spacing: 2px; }
  .sub-header { font-size: 10px; color: #555; }
  table { width: 100%; border-collapse: collapse; }
  table th { font-size: 10px; text-transform: uppercase; color: #555; border-bottom: 1px solid #ccc; padding: 3px 0; }
  table td { padding: 3px 0; vertical-align: top; }
  .extras { font-size: 10px; color: #777; padding-left: 8px; }
  .totals-table td { padding: 2px 0; }
  .grand-total td { font-size: 14px; font-weight: bold; border-top: 1px solid #111; padding-top: 4px; }
  .payment-confirmed { text-align: center; border: 2px solid #4CB050; border-radius: 6px; padding: 4px 8px; color: #4CB050; font-weight: bold; font-size: 11px; margin: 8px 0; }
  .footer { text-align: center; font-size: 10px; color: #888; margin-top: 6px; }
  .powered { text-align: center; font-size: 9px; color: #bbb; }
  @media print {
    body * { visibility: hidden; }
    .receipt, .receipt * { visibility: visible; }
    .receipt { position: fixed; top: 0; left: 0; margin: 0; padding: 4px; font-size: 11px; }
    .no-print { display: none !important; }
  }
</style>
</head>
<body>
<div class="receipt" id="receipt">

  <!-- Header -->
  <div class="center" style="margin-bottom:8px;">
    <?php if (!empty($order['logo_url'])): ?>
    <img src="<?php echo esc_url($order['logo_url']); ?>" alt="Logo" style="height:40px;margin-bottom:4px;">
    <?php endif; ?>
    <div class="logo-name">BYMOREISH</div>
    <div class="sub-header">Behind Marlima</div>
    <div class="sub-header"><?php echo esc_html($order['phone'] ?? ''); ?></div>
  </div>

  <hr class="divider-solid">

  <!-- Order Meta -->
  <table style="margin-bottom:4px;">
    <tr>
      <td>Order #:</td>
      <td class="right bold"><?php echo esc_html($order['order_number'] ?? ($order['id'] ?? '–')); ?></td>
    </tr>
    <tr>
      <td>Date:</td>
      <td class="right"><?php echo esc_html(isset($order['created_at']) ? date('d/m/Y', strtotime($order['created_at'])) : ''); ?></td>
    </tr>
    <tr>
      <td>Time:</td>
      <td class="right"><?php echo esc_html(isset($order['created_at']) ? date('h:i A', strtotime($order['created_at'])) : ''); ?></td>
    </tr>
    <tr>
      <td>Staff:</td>
      <td class="right"><?php echo esc_html($order['staff_name'] ?? ''); ?></td>
    </tr>
    <?php if (!empty($order['customer_name'])): ?>
    <tr>
      <td>Customer:</td>
      <td class="right"><?php echo esc_html($order['customer_name']); ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($order['customer_phone'])): ?>
    <tr>
      <td>Phone:</td>
      <td class="right"><?php echo esc_html($order['customer_phone']); ?></td>
    </tr>
    <?php endif; ?>
  </table>

  <hr class="divider">

  <!-- Items -->
  <table>
    <thead>
      <tr>
        <th style="text-align:left;width:40%;">Item</th>
        <th style="text-align:center;width:10%;">Qty</th>
        <th style="text-align:right;width:22%;">Price</th>
        <th style="text-align:right;width:28%;">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $subtotal = 0;
      if (!empty($order_items)):
        foreach ($order_items as $item):
          $line_total = floatval($item['unit_price'] ?? 0) * intval($item['qty'] ?? 1);
          $subtotal += $line_total;
      ?>
      <tr>
        <td><?php echo esc_html($item['name'] ?? ''); ?></td>
        <td style="text-align:center;"><?php echo intval($item['qty'] ?? 1); ?></td>
        <td style="text-align:right;">₦<?php echo number_format(floatval($item['unit_price'] ?? 0), 2); ?></td>
        <td style="text-align:right;">₦<?php echo number_format($line_total, 2); ?></td>
      </tr>
      <?php if (!empty($item['extras'])): ?>
      <tr><td colspan="4" class="extras">
        <?php
        $extras = is_string($item['extras']) ? json_decode($item['extras'], true) : $item['extras'];
        if (is_array($extras)) {
          foreach ($extras as $extra) {
            echo esc_html(' + ' . ($extra['name'] ?? '') . ' +₦' . number_format(floatval($extra['price'] ?? 0), 2)) . '<br>';
            $subtotal += floatval($extra['price'] ?? 0) * intval($item['qty'] ?? 1);
          }
        }
        ?>
      </td></tr>
      <?php endif; ?>
      <?php endforeach; endif; ?>
    </tbody>
  </table>

  <hr class="divider">

  <!-- Totals -->
  <?php
  $grand_total = floatval($order['total'] ?? $subtotal);
  $payment_mode = $order['payment_mode'] ?? '';
  $payment_breakdown = [];
  if (!empty($order['cash'])) $payment_breakdown['Cash'] = floatval($order['cash']);
  if (!empty($order['card'])) $payment_breakdown['Card'] = floatval($order['card']);
  if (!empty($order['transfer'])) $payment_breakdown['Transfer'] = floatval($order['transfer']);
  ?>
  <table class="totals-table">
    <tr>
      <td>Subtotal:</td>
      <td class="right">₦<?php echo number_format($subtotal, 2); ?></td>
    </tr>
    <?php foreach ($payment_breakdown as $mode => $amount): ?>
    <tr>
      <td><?php echo esc_html($mode); ?>:</td>
      <td class="right">₦<?php echo number_format($amount, 2); ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($payment_breakdown) && $payment_mode): ?>
    <tr>
      <td>Payment (<?php echo esc_html(ucfirst($payment_mode)); ?>):</td>
      <td class="right">₦<?php echo number_format($grand_total, 2); ?></td>
    </tr>
    <?php endif; ?>
    <tr class="grand-total">
      <td>GRAND TOTAL:</td>
      <td class="right">₦<?php echo number_format($grand_total, 2); ?></td>
    </tr>
  </table>

  <!-- Payment Confirmed -->
  <div class="payment-confirmed">✓ PAYMENT CONFIRMED</div>

  <hr class="divider">

  <!-- Footer -->
  <div class="footer">Thank you for dining with us!</div>
  <div class="footer" style="margin-top:4px;">We hope to see you again soon 😊</div>

  <hr class="divider">
  <div class="powered">Powered by Bymoreish POS</div>

</div>

<?php if (!empty($_GET['autoprint']) && intval($_GET['autoprint']) === 1): ?>
<script>
window.addEventListener('load', function() {
  setTimeout(function() { window.print(); }, 500);
});
</script>
<?php endif; ?>
</body>
</html>
