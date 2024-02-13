<?php

/**
 *
 * Paylabs payment plugin
 *
 * @author Jeremy Magne
 * @author Timur Pratama Wiradarma
 * @package VirtueMart
 * @subpackage payment
 * Copyright (C) 2016 Paylabs Team. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 * http://virtuemart.net 
 */
defined('_JEXEC') or die();
$success = $viewData["success"];
$payment_name = $viewData["payment_name"];
$order = $viewData["order"];
$currency = $viewData["currency"];
$reference = $viewData["reference"];

?>
<br />
<table>
	<tr>
		<td><?php echo vmText::_('VMPAYMENT_PAYLABS_API_PAYMENT_NAME'); ?></td>
		<td><?php echo $payment_name; ?></td>
	</tr>

	<tr>
		<td><?php echo vmText::_('COM_VIRTUEMART_ORDER_NUMBER'); ?></td>
		<td><?php echo $order['details']['BT']->order_number; ?></td>
	</tr>
	<?php if ($success) { ?>
		<!-- <tr>
		<td><?php echo vmText::_('VMPAYMENT_PAYLABS_API_AMOUNT'); ?></td>
        <td><?php echo intval($order['details']['BT']->order_total) ?></td>
    </tr> -->
		<tr>
			<td><?php echo vmText::_('VMPAYMENT_PAYLABS_API_TRANSACTION_ID'); ?></td>
			<td><?php echo $reference; ?></td>
		</tr>
		<tr>
			<td><?php echo vmText::_('VMPAYMENT_PAYLABS_API_COMMENT'); ?></td>
			<td><?php echo vmText::_('VMPAYMENT_PAYLABS_REDIRECT_SUCCES_MESSAGE'); ?></td>
		</tr>
	<?php } else { ?>
		<tr>
			<td><?php echo vmText::_('VMPAYMENT_PAYLABS_API_COMMENT'); ?></td>
			<td><?php echo vmText::_('VMPAYMENT_PAYLABS_REDIRECT_FAILED_MESSAGE'); ?></td>
		</tr>

	<?php } ?>

</table>
<?php if ($success) { ?>
	<br />
	<a class="vm-button-correct" href="<?php echo JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $viewData["order"]['details']['BT']->order_number . '&order_pass=' . $viewData["order"]['details']['BT']->order_pass, false) ?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?></a>
<?php } ?>