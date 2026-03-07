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
	<h3>{l s='CONFIGURATION' mod='girofeeds'}</h3>

	<div class="row">
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head">
				{l s='Order states "shipped":' mod='girofeeds'}
			</p>

			{foreach from=$order_states item=os}
				<input type="checkbox" name="os[shipped][]" value="{$os.id_order_state|escape:'html':'UTF-8'}" id="os_shipped_{$os.id_order_state|escape:'html':'UTF-8'}" {if $os.id_order_state|in_array:$order_states_shipped}checked{/if} /> <label for="os_shipped_{$os.id_order_state|escape:'html':'UTF-8'}">{$os.name|escape:'html':'UTF-8'}</label>
				<br />
			{/foreach}
		</div>

		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head">
				{l s='Order states "cancelled":' mod='girofeeds'}
			</p>

			{foreach from=$order_states item=os}
				<input type="checkbox" name="os[cancelled][]" value="{$os.id_order_state|escape:'html':'UTF-8'}" id="os_cancelled_{$os.id_order_state|escape:'html':'UTF-8'}" {if $os.id_order_state|in_array:$order_states_cancelled}checked{/if} /> <label for="os_cancelled_{$os.id_order_state|escape:'html':'UTF-8'}">{$os.name|escape:'html':'UTF-8'}</label>
				<br />
			{/foreach}
		</div>
	</div>
	<div class="row">
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Order state for imported orders:' mod='girofeeds'}
			</p>
			<select name="os_import" id="os_import">
				{foreach from=$order_states item=os}
					<option value="{$os.id_order_state|escape:'html':'UTF-8'}" {if $os.id_order_state == $order_state_import}selected{/if}>{$os.name|escape:'html':'UTF-8'}</option>
				{/foreach}
			</select>
		</div>
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Warehouse for imported orders:' mod='girofeeds'}
			</p>
			<select name="order_warehouse" id="order_warehouse">
				<option value="">---</option>
				{if isset($warehouses)}
					{foreach from=$warehouses item=wh}
						<option value="{$wh.id_warehouse|escape:'html':'UTF-8'}" {if $wh.id_warehouse == $order_warehouse}selected{/if}>{$wh.name|escape:'html':'UTF-8'}</option>
					{/foreach}
				{/if}
			</select>
		</div>
	</div>
	<div class="row">
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Carrier to use for generated orders:' mod='girofeeds'}
			</p>
			<select name="carrier_import" id="carrier_import">
				<option value="0">---</option>
				{foreach from=$carriers item=c}
					<option value="{$c.id_carrier|escape:'html':'UTF-8'}" {if $c.id_carrier == $order_carrier_import}selected{/if}>{$c.name|escape:'html':'UTF-8'}</option>
				{/foreach}
			</select>
		</div>
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Taxrate to calculate imported shipping price excl. tax:' mod='girofeeds'}
			</p>
			<input type="text" name="carrier_import_tax" id="carrier_import_tax" value="{$carrier_import_tax|escape:'htmlall':'UTF-8'}">
		</div>
	</div>
	<div class="row">
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Use Girofeeds order comment as Private Note' mod='girofeeds'}
			</p>
			<select name="comment_as_note" id="comment_as_note">
				<option value="0">No</option>
				<option value="1" {if Configuration::get('GIROFEEDS_COMMENT_AS_NOTE') == '1'}selected{/if}>Yes</option>
			</select>
		</div>
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Use Girofeeds order comment as Customer Thread' mod='girofeeds'}
			</p>
			<select name="comment_as_customer_thread" id="comment_as_customer_thread">
				<option value="0">No</option>
				<option value="1" {if Configuration::get('GIROFEEDS_COMMENT_AS_CUSTOMER_THREAD') == '1'}selected{/if}>Yes</option>
			</select>
			</div>
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Show Girofeeds order info in order-view grid' mod='girofeeds'} <i style="font-weight: normal">{l s='Only available in PS1.7.6 and higher' mod='girofeeds'}</i>
			</p>
			<select name="order_view_grid" id="order_view_grid">
				<option value="0">No</option>
				<option value="1" {if Configuration::get('GIROFEEDS_EXTEND_ORDER_VIEW_GRID') == '1'}selected{/if}>Yes</option>
			</select>
		</div>
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Replace characters in names at order creation' mod='girofeeds'} <i style="font-weight: normal">{l s='Use if you encounter problems with validation of customer names' mod='girofeeds'}</i>
			</p>
			<select name="enable_char_replacement" id="enable_char_replacement">
				<option value="0">No</option>
				<option value="1" {if Configuration::get('GIROFEEDS_REPLACE_NAME_CHARACTERS') == '1'}selected{/if}>Yes</option>
			</select>
		</div>
		<div class="col-xs-12 col-sm-6">
		  <p class="girofeeds_orderstates_config_head girofeeds_top_marged">
			{l s='Call newOrder/actionValidateOrder-hook after processing order from Girofeeds' mod='girofeeds'}
		  </p>
		  <select name="enable_new_order_hook" id="enable_new_order_hook">
			<option value="0">No</option>
			<option value="1" {if Configuration::get('GIROFEEDS_ENABLE_NEW_ORDER_HOOK') == '1'}selected{/if}>Yes</option>
		  </select>
		</div>
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Employee to be used for order creation' mod='girofeeds'}
			</p>
			<select name="employee_id" id="employee_id">
				<option value="0">-- none (could lead to errors at automatic stock updates) --</option>
				{foreach from=$employees item=e}
					<option value="{$e.id_employee|escape:'html':'UTF-8'}" {if $e.id_employee == $employee_id}selected{/if}>{$e.lastname|escape:'html':'UTF-8'} {$e.firstname|escape:'html':'UTF-8'}</option>
				{/foreach}
			</select>
		</div>
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
				{l s='Default string for orders with empty name fields:' mod='girofeeds'}
			</p>
			<input type="text" name="order_import_name_default" id="order_import_name_default" value="{$order_import_name_default|escape:'htmlall':'UTF-8'}">
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
		<div class="col-xs-12 col-sm-6">
			<p class="girofeeds_orderstates_config_head girofeeds_top_marged">
				{l s='Use submitted phone number as mobile number:' mod='girofeeds'}
			</p>
			<select name="enable_phone_as_mobile" id="enable_phone_as_mobile">
				<option value="0">No</option>
				<option value="1" {if Configuration::get('GIROFEEDS_USE_PHONE_FOR_MOBILE') == '1'}selected{/if}>Yes</option>
			</select>
		</div>
	</div>
	<div class="panel-footer">
		<button type="submit" value="1"	id="module_form_submit_btn" name="submitGirofeedsOrderSettingsModule" class="btn btn-default pull-right">
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
	<h3>{l s='Webhook-URL & Order-API-URL' mod='girofeeds'}</h3>
	<p>
		{$webhook_url|replace:'%2C':','|escape:'html':'UTF-8'}<br />
		{$order_api_url|replace:'%2C':','|escape:'html':'UTF-8'}<br />
		{$order_api_fetch_url|replace:'%2C':','|escape:'html':'UTF-8'}<br />
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

<form method="post" action="{$form_url|escape:'html':'UTF-8'}">
  <div class="panel">
    <h3>{l s='Expert: Assign customers to specific groups' mod='girofeeds'}</h3>

    <div class="row">
      <div class="col-xs-12 col-md-6">
        <h4>{l s='Assigned fields:' mod='girofeeds'}</h4>

        <p>
          {l s='If you want to put customers from specific marketplaces into specific customer groups, you can configure it with the following fields.' mod='girofeeds'}<br>
          {l s='Just enter the string that should be contained inside the channel name, and to which customer group the customer should be added.' mod='girofeeds'}<br>
        </p>

        <div id="customer_group_fields">
          <div class="row">
            <div class="col-xs-6"><strong>{l s='String in channel name contains:' mod='girofeeds'}</strong></div>
            <div class="col-xs-6"><strong>{l s='Group to put customer in:' mod='girofeeds'}</strong></div>
          </div>
          {foreach from=$customer_group_assignments item=cga key=nr}
            <div class="row">
              <div class="col-xs-6"><input type="text" name="cga[{$nr|escape:'htmlall':'UTF-8'}][s]" value="{$cga.s|escape:'html':'UTF-8'}"></div>
              <div class="col-xs-6">
                <select name="cga[{$nr|escape:'htmlall':'UTF-8'}][g]" class="cga_selector">
                  <option value="0">---</option>
                  {foreach from=$customer_groups item=cg}
                    <option value="{$cg.id_group|escape:'htmlall':'UTF-8'}" {if $cg.id_group == $cga.g}selected{/if}>{$cg.name|escape:'html':'UTF-8'}</option>
                  {/foreach}
                </select>
              </div>
            </div>
          {/foreach}

        </div>

      </div>
    </div>
    <div class="panel-footer">
      <button type="submit" value="1" id="module_customergroup_assignment_form_submit_btn" name="submitGirofeedsCustomergroupAssignmentModule" class="btn btn-default pull-right">
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


<form method="post" action="{$form_url|escape:'html':'UTF-8'}">
	<div class="panel">
		<h3>{l s='Expert: Assign marketplace to specific shipping status' mod='girofeeds'}</h3>

		<div class="row">
			<div class="col-xs-12 col-md-6">
				<div id="marketplace_shipping_fields">
					<div class="row">
						<div class="col-xs-6"><strong>{l s='String in channel name contains:' mod='girofeeds'}</strong></div>
						<div class="col-xs-6"><strong>{l s='Shipping status to set:' mod='girofeeds'}</strong></div>
					</div>
					{foreach from=$marketplace_assignments item=msa key=nr}
						<div class="row">
							<div class="col-xs-6"><input type="text" name="msa[{$nr|escape:'htmlall':'UTF-8'}][s]" value="{$msa.s|escape:'html':'UTF-8'}"></div>
							<div class="col-xs-6">
								<select name="msa[{$nr|escape:'htmlall':'UTF-8'}][g]" class="msa_selector">
									<option value="0">---</option>
									{foreach from=$order_states item=os}
										<option value="{$os.id_order_state|escape:'html':'UTF-8'}" {if $os.id_order_state == $msa.g}selected{/if}>{$os.name|escape:'html':'UTF-8'}</option>
									{/foreach}
								</select>
							</div>
						</div>
					{/foreach}

				</div>

			</div>
		</div>
		<div class="panel-footer">
			<button type="submit" value="1"	id="module_marketplace_assignment_form_submit_btn" name="submitGirofeedsMarketplaceAssignmentModule" class="btn btn-default pull-right">
				<i class="process-icon-save"></i> {l s='Save' mod='girofeeds'}
			</button>
		</div>
	</div>
</form>


<form method="post" action="{$form_url|escape:'html':'UTF-8'}">
	<div class="panel">
		<h3>{l s='Expert: Assign carriers based on product or category' mod='girofeeds'}</h3>

		<div class="row">
			<div class="col-xs-12 col-md-6">
				<div id="carriers_fields">
					<div class="row">
						<div class="col-xs-4"><strong>{l s='Entity (product or category):' mod='girofeeds'}</strong></div>
						<div class="col-xs-4"><strong>{l s='Entity ID:' mod='girofeeds'}</strong></div>
						<div class="col-xs-4"><strong>{l s='Assigned Carrier:' mod='girofeeds'}</strong></div>
					</div>
					{foreach from=$carrier_assignments item=csa key=nr}
						<div class="row">
							<div class="col-xs-4">
								<select name="csa[{$csa.id|escape:'htmlall':'UTF-8'}][entity_type]" class="carrier_entity_selector">
									<option value="product" {if $csa.entity_type == 'product'}selected{/if}>{l s='Product' mod='girofeeds'}</option>
									<option value="category" {if $csa.entity_type == 'category'}selected{/if}>{l s='Category' mod='girofeeds'}</option>
								</select>
							</div>
							<div class="col-xs-4">
								<input type="text" inputmode="numeric" name="csa[{$csa.id|escape:'htmlall':'UTF-8'}][id_entity]" value="{$csa.id_entity|escape:'html':'UTF-8'}">
							</div>
							<div class="col-xs-4">
								<select name="csa[{$csa.id|escape:'htmlall':'UTF-8'}][id_carrier]" class="carrier_selector">
									<option value="0">---</option>
									{foreach from=$carriers item=c}
										<option value="{$c.id_carrier|escape:'html':'UTF-8'}" {if $c.id_carrier == $csa.id_carrier}selected{/if}>{$c.name|escape:'html':'UTF-8'}</option>
									{/foreach}
								</select>
							</div>
						</div>
					{/foreach}

				</div>
				<p>
					{l s='To remove an assignment, just set the entity ID to 0 or empty.' mod='girofeeds'}
				</p>
			</div>
		</div>
		<div class="panel-footer">
			<button type="submit" value="1"	id="module_carrier_assignment_form_submit_btn" name="submitGirofeedsCarrierAssignmentModule" class="btn btn-default pull-right">
				<i class="process-icon-save"></i> {l s='Save' mod='girofeeds'}
			</button>
		</div>
	</div>
</form>


<form method="post" action="{$form_url|escape:'html':'UTF-8'}">
	<div class="panel">
		<h3>{l s='Expert: Taxrate to calculate imported shipping price excl. tax based on delivery address country' mod='girofeeds'}</h3>

		<div class="row">
			<div class="col-xs-12 col-md-6">
				<div id="carriers_fields">
					<div class="row">
						<div class="col-xs-4"><strong>{l s='Country:' mod='girofeeds'}</strong></div>
						<div class="col-xs-4"><strong>{l s='Tax rate:' mod='girofeeds'}</strong></div>
					</div>
					{foreach from=$tax_country_assignments item=coa key=nr}
						<div class="row {if $nr >= 5}hidden_row_tax{/if}" {if $nr >= 5}style="display:none;"{/if}>
							<div class="col-xs-4">
								<select name="coa[{$nr|escape:'htmlall':'UTF-8'}][country_id]" class="country_entity_selector">
									<option value="0">---</option>
									{foreach from=$shop_countries item=country}
										<option value="{$country.id_country|escape:'htmlall':'UTF-8'}" {if $country.id_country == $coa.country_id}selected{/if}>{$country.name|escape:'htmlall':'UTF-8'}</option>
									{/foreach}
								</select>
							</div>
							<div class="col-xs-4">
								<input type="text" inputmode="numeric" name="coa[{$nr|escape:'htmlall':'UTF-8'}][tax_rate]" value="{$coa.tax_rate|escape:'html':'UTF-8'}">
							</div>
						</div>
					{/foreach}

				</div>
				<a href="JavaScript:void(0)" id="show_more_fields_link">
					{l s='Show more fields' mod='girofeeds'}
				</a>
				<p>
					{l s='To remove an assignment, just set the Tax rate to empty.' mod='girofeeds'}
				</p>
			</div>
		</div>
		<div class="panel-footer">
			<button type="submit" value="1"	id="module_texrate_assignment_form_submit_btn" name="submitGirofeedsTaxRateModule" class="btn btn-default pull-right">
				<i class="process-icon-save"></i> {l s='Save' mod='girofeeds'}
			</button>
		</div>
	</div>
</form>