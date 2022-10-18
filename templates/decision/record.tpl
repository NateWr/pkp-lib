{**
 * templates/management/record.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief A step-by-step UI to record an editorial decision
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<div class="app__page--decision">
		<h1 class="app__pageHeading" ref="pageTitle">
			<template v-if="steps.length > 1">
				{translate key="semicolon" label=$decisionType->getLabel()}
				{{ currentStep.name }}
			</template>
			<template v-else>
				{$decisionType->getLabel()}
			</template>
		</h1>
		<p class="app__pageDescription">
			{$decisionType->getDescription()}
		</p>

		<notification
			v-for="(error, stepId) in errors"
			:key="stepId"
			type="warning"
			class="decision__error"
			role="alert"
		>
			{{ error }}
			<button class="-linkButton" @click="openStep(stepId)">
				{translate key="common.viewError"}
			</button>
		</notification>

		<steps
			v-if="steps.length"
			:current="currentStep.id"
			:started-steps="startedSteps"
			label="{translate key="editor.decision.completeSteps"}"
			progress-label="{translate key="common.showingSteps"}"
			:scroll-to="$refs.pageTitle"
			show-steps-label="{translate key="common.showAllSteps"}"
			@step:open="openStep"
		>
			<step
				v-for="step in steps"
				:key="step.id"
				:id="step.id"
				:label="step.name"
			>
				<template v-if="step.type === 'form'">
					<panel>
						<panel-section>
							<template slot="header">
								<h2>{{ step.name }}</h2>
								<p>{{ step.description }}</p>
							</template>
							<pkp-form v-bind="step.form" @set="updateStep"></pkp-form>
						</panel-section>
					</panel>
				</template>
				<template v-else-if="step.type === 'email'">
					<panel v-if="skippedSteps.includes(step.id)">
						<panel-section>
							<template slot="header">
								<h2>{{ step.name }}</h2>
								<p>{translate key="editor.decision.emailSkipped"}</p>
							</template>
						</panel-section>
					</panel>
					{capture assign="attachedFilesLabel"}{translate key="common.attachedFiles"}{/capture}
					{capture assign="bccLabel"}{translate key="email.bcc"}{/capture}
					{capture assign="ccLabel"}{translate key="email.cc"}{/capture}
					{capture assign="subjectLabel"}{translate key="email.subject"}{/capture}
					{capture assign="switchTolabel"}{translate key="common.switchTo"}{/capture}
					{capture assign="recipientsLabel"}{translate key="email.to"}{/capture}
					<composer
						v-else
						add-c-c-label="{translate key="common.addCCBCC"}"
						attach-files-label="{translate key="common.attachFiles"}"
						attached-files-label="{translate key="semicolon" label=$attachedFilesLabel}"
						:attachers="step.attachers"
						:attachments="step.attachments"
						:bcc="step.bcc"
						bcc-label="{translate key="semicolon" label=$bccLabel}"
						:body="step.body"
						body-label="{translate key="stageParticipants.notify.message"}"
						:can-change-recipients="step.canChangeRecipients"
						:cc="step.cc"
						cc-label="{translate key="semicolon" label=$ccLabel}"
						confirm-switch-locale-label="{translate key="email.confirmSwitchLocale"}"
						deselect-label="{translate key="common.deselect"}"
						:email-templates="step.emailTemplates"
						:email-templates-api-url="emailTemplatesApiUrl"
						find-template-label="{translate key="common.findTemplate"}"
						:id="step.id"
						:initial-template-key="step.initialTemplateKey"
						insert-label="{translate key="common.insert"}"
						insert-modal-label="{translate key="common.insertContent"}"
						insert-content-label="{translate key="common.content"}"
						insert-search-label="{translate key="common.insertContentSearch"}"
						load-template-label="{translate key="common.loadTemplate"}"
						:locale="step.locale"
						:locales="step.locales"
						more-search-results-label="{translate key="common.numberedMore"}"
						:recipient-options="step.recipientOptions"
						:recipients="step.recipients"
						recipients-label="{translate key="semicolon" label=$recipientsLabel}"
						remove-item-label="{translate key="common.removeItem"}"
						searching-label="{translate key="common.searching"}"
						search-results-label="{translate key="search.searchResults"}"
						:subject="step.subject"
						subject-label="{translate key="semicolon" label=$subjectLabel}"
						switch-to-label="{translate key="semicolon" label=$switchTolabel}"
						switch-to-named-language-label="{translate key="common.switchToNamedItem"}"
						:variables="step.variables"
						@set="updateStep"
					>

						<template slot="description">
							<h2>{{ step.name }}</h2>
							<p v-html="step.description"></p>
						</template>
					</composer>
				</template>
				<template v-else-if="step.type === 'promoteFiles'">
					<panel>
						<panel-section>
							<template slot="header">
								<h2>{{ step.name }}</h2>
								<p>{{ step.description }}</p>
							</template>
							<list-panel
								v-for="(list, i) in step.lists"
								:key="i"
								:items="list.files"
								:title="list.name"
							>
								<template v-slot:item="{ldelim}item{rdelim}">
									<select-submission-file-list-item
										:document-type="item.documentType"
										download-label="{translate key="common.download"}"
										:genre-name="localize(item.genreName)"
										:genre-is-primary="!item.genreIsDependent && !item.genreIsSupplementary"
										:file-id="item.id"
										:name="localize(item.name)"
										:url="item.url"
									>
										<input
											type="checkbox"
											:name="'promoteFile' + item.id"
											:value="item.id"
											v-model="step.selected"
										></input>
									</select-submission-file-list-item>
								</template>
							</list-panel>
						</panel-section>
					</panel>
				</template>
			</step>
		</steps>

		<button-row
			class="decision__footer"
			:class="{ldelim}'decision__footer--noSteps': !steps.length{rdelim}"
		>
			<template slot="end">
				<button
					v-if="currentStep.type === 'email'"
					class="decision__skipStep -linkButton"
					:disabled="isSubmitting"
					@click="toggleSkippedStep(currentStep.id)"
				>
					<template v-if="skippedSteps.includes(currentStep.id)">
						{translate key="editor.decision.dontSkipEmail"}
					</template>
					<template v-else>
						{translate key="editor.decision.skipEmail"}
					</template>
				</button>
				<!--
					Leave this v-else in so that the slot
					is never empty.
				-->
				<span v-else></span>
			</template>
			<spinner v-if="isSubmitting"></spinner>
			<pkp-button
				:disabled="isSubmitting"
				:is-warnable="true"
				@click="cancel"
			>
				{translate key="common.cancel"}
			</pkp-button>
			<pkp-button
				v-if="!isOnFirstStep && steps.length > 1"
				:disabled="isSubmitting"
				@click="previousStep"
			>
				{translate key="help.previous"}
			</pkp-button>
			<pkp-button
				:disabled="isSubmitting"
				:is-primary="isOnLastStep"
				@click="nextStep"
			>
				<template v-if="isOnLastStep">
					{translate key="editor.decision.recordDecision"}
				</template>
				<template v-else>
					{translate key="help.next"}
				</template>
			</pkp-button>
		</button-row>
	</div>

{/block}