/**
 * @file cypress/tests/integration/Filenames.spec.js
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

 describe('Filename support for different character sets', function() {
	it('#6898 Tests the download filename is correct after stripping characters', function() {
		cy.login('dbarnes');
		cy.visit('index.php/publicknowledge/workflow/access/1');
		cy.get('.ui-tabs-anchor').contains('Submission').eq(0).click();
		cy.get('#submissionFilesGridDiv .show_extras').eq(0).click();
		cy.get('[id^="component-grid-files-submission"]').contains('Edit').eq(0).click();
		cy.get('[name="name[en_US]"').clear().type('edição-&£$L<->/4/ch 丹尼爾 a دانيال1d line \\n break.pdf');
		cy.get('[name="name[fr_CA]"').clear().type('edição-&£$L<->/4/ch 丹尼爾 a دانيال1d line \\n break.pdf');
		cy.get('[id^="submitFormButton"]').contains('Save').click();

		cy.request('GET', 'index.php/publicknowledge/$$$call$$$/api/file/file-api/download-file?submissionFileId=1&submissionId=1&stageId=1')
			.then((response) => {
				expect(response.headers).to.have.property('content-disposition', 'attachment; filename="ediÃ§Ã£o-&Â£$l<->/4/ch-ä¸¹å°¼ç¾-a-Ø¯Ø§ÙÙØ§Ù1d-line-\n-break.pdf"');
			});
	});
});