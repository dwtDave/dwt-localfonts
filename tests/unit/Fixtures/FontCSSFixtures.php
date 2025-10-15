<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Fixtures;

/**
 * Test fixtures for font CSS data
 *
 * Provides realistic CSS samples for testing CSS parsing and font URL extraction.
 */
class FontCSSFixtures {

	/**
	 * Valid Google Fonts CSS with single @font-face rule (Roboto Regular)
	 */
	public static function getValidGoogleFontsCSS(): string {
		return <<<'CSS'
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS;
	}

	/**
	 * CSS with multiple weights/styles (Roboto: 400, 700, 400italic)
	 */
	public static function getValidMultiVariantCSS(): string {
		return <<<'CSS'
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 700;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlfBBc4.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Roboto';
  font-style: italic;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOkCnqEu92Fr1Mu51xIIzI.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS;
	}

	/**
	 * CSS with unicode-range subsets (Latin, Latin-ext, Cyrillic)
	 */
	public static function getValidSubsetCSS(): string {
		return <<<'CSS'
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK-latin.woff2) format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK-latin-ext.woff2) format('woff2');
  unicode-range: U+0100-024F, U+0259, U+1E00-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF;
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK-cyrillic.woff2) format('woff2');
  unicode-range: U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116;
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 700;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlfBBc4-latin.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 700;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlfBBc4-latin-ext.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
CSS;
	}

	/**
	 * Empty string for testing validation
	 */
	public static function getEmptyCSS(): string {
		return '';
	}

	/**
	 * Valid CSS but no @font-face rules
	 */
	public static function getNoFontFaceCSS(): string {
		return <<<'CSS'
body {
  font-family: 'Roboto', sans-serif;
  color: #333;
}

h1 {
  font-weight: 700;
}
CSS;
	}

	/**
	 * @font-face with invalid URL format
	 */
	public static function getMalformedURLCSS(): string {
		return <<<'CSS'
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url(not-a-valid-url) format('woff2');
}
CSS;
	}

	/**
	 * @font-face without font-family property
	 */
	public static function getMissingFontFamilyCSS(): string {
		return <<<'CSS'
@font-face {
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2) format('woff2');
}
CSS;
	}

	/**
	 * Valid CSS from alternative CDN (Bunny Fonts)
	 */
	public static function getBunnyFontsCSS(): string {
		return <<<'CSS'
@font-face {
  font-family: 'Open Sans';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.bunny.net/open-sans/files/open-sans-latin-400-normal.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS;
	}

	/**
	 * Font family with special characters and spaces
	 */
	public static function getSpecialCharsFamilyCSS(): string {
		return <<<'CSS'
@font-face {
  font-family: 'Montserrat Alternates';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/montserratalternates/v17/mFThWacfw6zH4dthXcyms1lPpC8I_b0juU0xiKfVKphL03l4.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
CSS;
	}

	/**
	 * CSS with 10+ @font-face rules for comprehensive variant testing
	 */
	public static function getMultipleVariantsCSS(): string {
		return <<<'CSS'
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 300;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmSU5fBBc4-latin.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Roboto';
  font-style: italic;
  font-weight: 300;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOjCnqEu92Fr1Mu51TjASc6-latin.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK-latin.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Roboto';
  font-style: italic;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOkCnqEu92Fr1Mu51xIIzI-latin.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 700;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlfBBc4-latin.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Roboto';
  font-style: italic;
  font-weight: 700;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOjCnqEu92Fr1Mu51TzBic6-latin.woff2) format('woff2');
  unicode-range: U+0000-00FF;
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 300;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmSU5fBBc4-latin-ext.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
@font-face {
  font-family: 'Roboto';
  font-style: italic;
  font-weight: 300;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOjCnqEu92Fr1Mu51TjASc6-latin-ext.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK-latin-ext.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
@font-face {
  font-family: 'Roboto';
  font-style: italic;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOkCnqEu92Fr1Mu51xIIzI-latin-ext.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 700;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlfBBc4-latin-ext.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
@font-face {
  font-family: 'Roboto';
  font-style: italic;
  font-weight: 700;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOjCnqEu92Fr1Mu51TzBic6-latin-ext.woff2) format('woff2');
  unicode-range: U+0100-024F;
}
CSS;
	}

	/**
	 * CSS with quoted URL variants (single and double quotes)
	 */
	public static function getQuotedURLVariantsCSS(): string {
		return <<<'CSS'
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url("https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2") format('woff2');
}
@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 700;
  src: url('https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlfBBc4.woff2') format('woff2');
}
CSS;
	}
}
