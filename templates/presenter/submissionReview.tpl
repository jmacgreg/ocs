{**
 * submissionReview.tpl
 *
 * Copyright (c) 2003-2005 The Public Knowledge Project
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Presenter's submission review.
 *
 * $Id$
 *}

{if $stage==REVIEW_PROGRESS_ABSTRACT}
	{assign var="pageCrumbTitle" value="submission.abstractReview"}
	{translate|assign:"pageTitleTranslated" key="submission.page.abstractReview" id=$submission->getPaperId()}
{else}{* REVIEW_PROGRESS_PAPER *}
	{assign var="pageCrumbTitle" value="submission.paperReview"}
	{translate|assign:"pageTitleTranslated" key="submission.page.paperReview" id=$submission->getPaperId()}
{/if}

{include file="common/header.tpl"}

<ul class="menu">
	<li><a href="{url op="submission" path=$submission->getPaperId()}">{translate key="submission.summary"}</a></li>
	{if $schedConfSettings.reviewPapers}
		<li {if $stage==REVIEW_PROGRESS_ABSTRACT}class="current"{/if}><a href="{url op="submissionReview" path=$submission->getPaperId()|to_array:$smarty.const.REVIEW_PROGRESS_ABSTRACT}">
			{translate key="submission.abstractReview"}</a>
		</li>
		<li {if $stage==REVIEW_PROGRESS_PAPER}class="current"{/if}><a href="{url op="submissionReview" path=$submission->getPaperId()|to_array:$smarty.const.REVIEW_PROGRESS_PAPER}">
			{translate key="submission.paperReview"}</a>
		</li>
	{else}
		<li><a href="{url op="submissionReview" path=$submission->getPaperId()|to_array:$smarty.const.REVIEW_PROGRESS_ABSTRACT}">{translate key="submission.review"}</a></li>
	{/if}
	<li><a href="{url op="submissionEditing" path=$submission->getPaperId()}">{translate key="submission.editing"}</a></li>
</ul>


{include file="presenter/submission/summary.tpl"}

<div class="separator"></div>

{include file="presenter/submission/peerReview.tpl"}

<div class="separator"></div>

{include file="presenter/submission/directorDecision.tpl"}

{include file="common/footer.tpl"}