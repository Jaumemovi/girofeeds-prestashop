{*
 * Girofeeds - Feed management module for PrestaShop
 * Based on the Channable addon by patworx multimedia GmbH (2007-2025, patworx.de)
 *
 *  @author    Moviendote <hello@girofeeds.com>
 *  @copyright 2025-2026 Moviendote
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *}

<div class="panel {if $isHigher176}card{/if}">
	{if $additionalData}
		<h3>{l s='Additional Girofeeds order data' mod='girofeeds'}</h3>
		<div class="{if $isHigher176}card-body{/if}">
			<form method="post">
				<div class="table-responsive">
					<table class="table">
						<tbody>
							<tr>
								<td>
									Return Tracking Code:
								</td>
								<td>
									<input type="text" name="girofeeds_return_code" value="{$orderReturnCode|escape:'htmlall':'UTF-8'}" class="form-control" id="girofeeds_return_code">
								</td>
								<td>
									<button type="submit" class="btn btn-primary btn-sm">{l s='Save' mod='girofeeds'}</button>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</form>
			<div class="table-responsive">
				<table class="table">
					<thead>
						<tr>
							<th>
								<span class="title_box">
									{l s='Field' mod='girofeeds'}
								</span>
							</th>
							<th>
								<span class="title_box">
									{l s='Value' mod='girofeeds'}
								</span>
							</th>
						</tr>
					</thead>
					<tbody>
						{foreach from=$additionalData item=a}
							<tr>
								<td>
									{$a.field_in_post|escape:'htmlall':'UTF-8'}
								</td>
								<td>
									{$a.value_in_post|escape:'htmlall':'UTF-8'}
								</td>
							</tr>
						{/foreach}
					</tbody>
				</table>
			</div>
		</div>
	{/if}
</div>
