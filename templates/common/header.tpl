{**
 * lib/pkp/templates/common/header.tpl
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Common site header.
 *}
<!DOCTYPE html>
<html lang="{$currentLocale|replace:"_":"-"}" xml:lang="{$currentLocale|replace:"_":"-"}">
{if !$pageTitleTranslated}{capture assign=pageTitleTranslated}{translate key=$pageTitle}{/capture}{/if}
{include file="core:common/headerHead.tpl"}
<body class="pkp_page_{$requestedPage|escape|default:"index"} pkp_op_{$requestedOp|escape|default:"index"}" dir="{$currentLocaleLangDir|escape|default:"ltr"}">
	<script type="text/javascript">
		// Initialise JS handler.
		$(function() {ldelim}
			$('body').pkpHandler(
				'$.pkp.controllers.SiteHandler',
				{ldelim}
					toggleHelpUrl: {url|json_encode page="user" op="toggleHelp" escape=false},
					toggleHelpOnText: {$toggleHelpOnText|json_encode},
					toggleHelpOffText: {$toggleHelpOffText|json_encode},
					{include file="controllers/notification/notificationOptions.tpl"}
				{rdelim});
		{rdelim});
	</script>

	<div id="app" class="app {if $isLoggedInAs} app--isLoggedInAs{/if}" v-cloak>
		<header class="app__header" role="banner">
			{if $availableContexts}
				<dropdown class="app__headerAction app__contexts">
					<template slot="button">
						<icon icon="sitemap"></icon>
						<span class="-screenReader">{translate key="context.contexts"}</span>
					</template>
					<ul>
						{foreach from=$availableContexts item=$availableContext}
							{if $availableContext->name !== $currentContext->getLocalizedData('name')}
								<li>
									<a href="{$availableContext->url|escape}" class="pkpDropdown__action">
										{$availableContext->name|escape}
									</a>
								</li>
							{/if}
						{/foreach}
					</ul>
				</dropdown>
			{/if}
			<div class="app__contextTitle">
				{$currentContext->getLocalizedData('name')}
			</div>
			{if $currentUser}
				<div class="app__headerActions">
					<dropdown class="app__headerAction app__tasks">
						<template slot="button">
							{translate key="common.tasks"}
							<badge>1</badge>
						</template>
						<div style="width: 400px; height: 300px;">
							... tasks grid ...
						</div>
					</dropdown>
					<dropdown class="app__headerAction app__userNav">
						<template slot="button">
							<icon icon="user-circle-o"></icon>
							{if $isUserLoggedInAs}
								<icon icon="user-circle" class="app__userNav__isLoggedInAsWarning"></icon>
							{/if}
							<span class="-screenReader">{$currentUser->getData('username')}</span>
						</template>
						<nav aria-label="{translate key="common.navigation.user"}">
							<div class="pkpDropdown__section">
								<div class="app__userNav__changeLocale">Change Language</div>
								<ul>
									{foreach from=$supportedLocales item="locale" key="localeKey"}
										<li>
											<a href="{url router=$smarty.const.ROUTE_PAGE page="user" op="setLocale" path=$localeKey}" class="pkpDropdown__action">
												{if $localeKey == $currentLocale}
													<icon icon="check" :inline="true"></icon>
												{/if}
												{$locale|escape}
											</a>
										</li>
									{/foreach}
								</ul>
							</div>
							{if $isUserLoggedInAs}
								<div class="pkpDropdown__section">
									<div class="app__userNav__loggedInAs">
										{translate key="manager.people.signedInAs" username=$currentUser->getData('username')}
										<a href="{url router=$smarty.const.ROUTE_PAGE page="login" op="signOutAsUser"}" class="app__userNav__logOutAs">{translate key="user.logOutAs" username=$currentUser->getData('username')}</a>.
									</div>
								</div>
							{/if}
							<div class="pkpDropdown__section">
								<ul>
									<li>
										<a href="{url router=$smarty.const.ROUTE_PAGE page="user" op="profile"}" class="pkpDropdown__action">
											{translate key="user.profile.editProfile"}
										</a>
									</li>
									<li>
										{if $isUserLoggedInAs}
											<a href="{url router=$smarty.const.ROUTE_PAGE page="login" op="signOutAsUser"}" class="pkpDropdown__action">
												{translate key="user.logOutAs" username=$currentUser->getData('username')}
											</a>
										{else}
											<a href="{url router=$smarty.const.ROUTE_PAGE page="login" op="signOut"}" class="pkpDropdown__action">
												{translate key="user.logOut"}
											</a>
										{/if}
									</li>
								</ul>
							</div>
						</nav>
					</dropdown>
				</div>
			{/if}
		</header>

		<div class="app__body">
			<nav v-if="!!menu" class="app__nav" aria-label="{translate key="common.navigation.site"}">
				<ul>
					<li v-for="(menuItem, key) in menu" :key="key" :class="!!menuItem.submenu ? 'app__navGroup' : ''">
						<div v-if="!!menuItem.submenu" class="app__navItem app__navItem--hasSubmenu">
							{{ menuItem.name }}
						</div>
						<a v-else class="app__navItem" :class="menuItem.isCurrent ? 'app__navItem--isCurrent' : ''" :href="menuItem.url">
							{{ menuItem.name }}
						</a>
						<ul v-if="!!menuItem.submenu">
							<li v-for="(submenuItem, submenuKey) in menuItem.submenu" :key="submenuKey">
								<a class="app__navItem" :class="submenuItem.isCurrent ? 'app__navItem--isCurrent' : ''" :href="submenuItem.url">
									{{ submenuItem.name }}
								</a>
							</li>
						</ul>
					</li>
				</ul>
			</nav>

			<main class="app__main">
				<div class="app__page">

					{** allow pages to provide their own titles **}
					{if !$suppressPageTitle}
						<div class="pkp_page_title">
							<h1>{$pageTitleTranslated}</h1>
						</div>
					{/if}
