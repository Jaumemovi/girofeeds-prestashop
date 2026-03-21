{*
 * Girofeeds - Feed management module for PrestaShop
 * Based on the Channable addon by patworx multimedia GmbH (2007-2025, patworx.de)
 *
 *  @author    Moviendote <hello@girofeeds.com>
 *  @copyright 2025-2026 Moviendote
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *}

{if isset($success_message)}
	<div class="alert alert-success">{$success_message|escape:'htmlall':'UTF-8'}</div>
{/if}

{if isset($update_key_message)}
	<div class="alert alert-warning">Please update your webservice key to ensure highest security.</div>
{/if}

<div class="panel">
	<h3>{l s='ABOUT GIROFEEDS' mod='girofeeds'}</h3>
	<img src="{$module_dir|escape:'html':'UTF-8'}views/img/girofeeds.png" class="img_responsive girofeeds_logo" />
	<p>
		{l s='Girofeeds offers a cloud-based datafeed management tool, which makes online advertisement much easier for online retailers and marketing agencies. Within the tool you can set up clever rules in order to create optimized product feeds and/or connect with the APIs of several platforms, such as Amazon or Admarkt. Free technical support is included.' mod='girofeeds'}
	</p>
	<div class="girofeeds_clear"></div>
	<h3>{l s='SEND YOUR PRESTASHOP ARTICLES TO MORE THAN 100 PRICE COMPARISON WEBSITES, AFFILIATES OR MARKETPLACES.' mod='girofeeds'}</h3>
	<p>
		{l s='You can generate more traffic for your webshop by creating ads with product information on comparison websites, affiliate networks or marketplaces like eBay, Marktplaats, Beslist.nl, Bol.com, Amazon.com and more. With the Girofeeds datafeed management tool you can easily control the flow of products to each channel. In this way, you can maximize the impact of your online campaign in one control center.' mod='girofeeds'}
	</p>
	<div class="girofeeds_clear"></div>
</div>

<form method="post" action="{$form_url|escape:'html':'UTF-8'}">
<div class="panel girofeeds_configuration">
	<h3>{l s='STOCK SETTINGS' mod='girofeeds'}</h3>

	<div class="row">
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Send product stock updates all X minutes to Girofeeds' mod='girofeeds'}
			</p>
			<select name="send_product_stock_interval" id="send_product_stock_interval">
				{foreach from=[5,10,15,30,45,60,120] item=minutes}
					<option value="{$minutes|escape:'htmlall':'UTF-8'}" {if Configuration::get('GIROFEEDS_CRON_BACKEND_TIMEDIFF_MIN') == $minutes}selected{/if}>{$minutes|escape:'htmlall':'UTF-8'}</option>
				{/foreach}
			</select>
		</div>
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Sync stock between shops in multishop environment:' mod='girofeeds'}
			</p>
			<select name="enable_shop_stock_sync" id="enable_shop_stock_sync">
				<option value="0">No</option>
				<option value="1" {if Configuration::get('GIROFEEDS_SHOP_STOCK_SYNC') == '1'}selected{/if}>Yes</option>
			</select>
		</div>
	</div>
	<div class="panel-footer">
		<button type="submit" value="1"	id="module_form_submit_btn" name="submitGirofeedsStockSettingsModule" class="btn btn-default pull-right">
			<i class="process-icon-save"></i> {l s='Save' mod='girofeeds'}
		</button>
	</div>
</div>
</form>

<div class="panel">
	<h3>{l s='Feed-URL' mod='girofeeds'}</h3>
	<p>
		{$feed_url|replace:'%2C':','|escape:'html':'UTF-8'}
	</p>
	<p>
		{l s='Key' mod='girofeeds'}: {$girofeeds_key|escape:'html':'UTF-8'}<br />
		{l s='Lang-ID' mod='girofeeds'}: {$lang_id|escape:'html':'UTF-8'}<br />
	</p>
</div>

<div class="panel">
	<h3>{l s='Webhook-URL' mod='girofeeds'}</h3>
	<p>
		{$webhook_url|replace:'%2C':','|escape:'html':'UTF-8'}<br />
	</p>
	<br>
	<h3>{l s='Product-Info-URL' mod='girofeeds'}</h3>
	<p>
		{$product_api_url|replace:'%2C':','|escape:'html':'UTF-8'}<br />
		{l s='Use PrestaShop product ID or reference as id_product-Parameter.' mod='girofeeds'}
	</p>
	<br>
	<h3>{l s='Call the following URLs via cronjob to build the feed cache:' mod='girofeeds'}</h3>
	<p>
		{$product_cache_cron_url|replace:'%2C':','|escape:'html':'UTF-8'}<br />
		{l s='It is recommended to call the URL each minute.' mod='girofeeds'}
	</p>
</div>


{$mainform nofilter} {* not escaped! comes from PS scripts *}


<form method="post" action="{$form_url|escape:'html':'UTF-8'}">
	<div class="panel">
		<h3>{l s='Expert: Additional fields in feed' mod='girofeeds'}</h3>

		<div class="row">
			<div class="col-xs-12 col-md-6">
				<h4>{l s='Assigned fields:' mod='girofeeds'}</h4>

				<div id="no_assign_fields_message">
					{l s='No fields assigned yet. Please use the menu "Avaiable fields".' mod='girofeeds'}
				</div>

				<div id="assigned_fields">
					<div id="assign_fields_head" style="display: none;" class="row">
						<div class="col-xs-3">{l s='Table' mod='girofeeds'}</div>
						<div class="col-xs-3">{l s='Field' mod='girofeeds'}</div>
						<div class="col-xs-6">{l s='Name in feed' mod='girofeeds'}</div>
					</div>
				</div>

			</div>
			<div class="col-xs-12 col-md-6">
				<h4>{l s='Available fields:' mod='girofeeds'}</h4>

				{l s='Please select the additional fields to export' mod='girofeeds'}

				<select name="fields_available">
					<option value=""></option>
					{foreach from=$feedfields_available key=fagroup item=faitems}
						<optgroup label="{$fagroup|escape:'html':'UTF-8'}">
							{foreach from=$faitems item=fa}
								<option value="{$fagroup|escape:'html':'UTF-8'}.{$fa|escape:'html':'UTF-8'}">{$fa|escape:'html':'UTF-8'}</option>
							{/foreach}
						</optgroup>
					{/foreach}
				</select>

				<button type="button" id="assign_field" class="btn btn-default">
					<i class="icon-chevron-right"></i> {l s='assign' mod='girofeeds'}
				</button>

			</div>
		</div>
		<div class="panel-footer">
			<button type="submit" value="1"	id="module_assignment_form_submit_btn" name="submitGirofeedsAssignmentModule" class="btn btn-default pull-right">
				<i class="process-icon-save"></i> {l s='Save' mod='girofeeds'}
			</button>
		</div>
	</div>
</form>

{if isset($feedfields_assigned) && is_array($feedfields_assigned)}
	<script>
		$(document).ready(function() {literal}{{/literal}
			{foreach from=$feedfields_assigned item=fa}
				addAssignedRow('{$fa.tablename|escape:'javascript':'UTF-8'}', '{$fa.field_in_db|escape:'javascript':'UTF-8'}', '{$fa.field_in_feed|escape:'javascript':'UTF-8'}');
			{/foreach}
		{literal}}{/literal});
	</script>
{/if}