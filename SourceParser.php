<?php

/***********
 *	
 *	SourceParser
 *	https://developers.urbanmonastic.org/
 *	
 *	© Paul Prins
 *	https://paulprins.net https://paul.build/
 *	
 *	Licensed under MIT - For full license, view the LICENSE distributed with this source.
 *	
 ***********/

class SourceParser{


	// Establish the version of the library
	const version = '0.1';


	/**
	 * Format the text and return it
	 *
	 * @param  string	$Text		The text object provided by the source
	 * @param  text		$Format		What is the desired output format
	 * @return HTML
	 */
	public function text( $Text ){
		$Elements = $this->textElements( $Text );

		# convert to markup
		$markup = $this->elements($Elements);

		# trim line breaks from front and end of string
		$markup = trim($markup, "\n");

		return $markup;
	}

	/**
	 * Format a line of text and return it
	 *
	 * @param  string	$Text		The text object provided by the source
	 * @param  array	$$nonNestables		What elements should we not nest
	 * @return HTML
	 */
	public function line( $text, $nonNestables = array() ){
		return $this->elements($this->lineElements($text, $nonNestables));
	}


	/*
	 *	Establish the variables
	 */
	protected $breaksEnabled = true;	// Convert \r\n into line breaks.
	protected $markupEscaped = true;	// Escape any HTML syntax in the text (if true convert < into &lt;).
	protected $liturgicalElements = false;	// Look for liturgical elements in the text
	protected $liturgicalHTML = true;	// Do we wrap liturgical elements in HTML tags
	protected $supressAlleluia = false;	// Do we remove the word Alleluia from the text
	protected $footnotesEnabled = false;	// Look for footnotes in the text
	protected $intercessionResponse;	

	private static $instances = array();
	protected $DefinitionData;

	protected $BlockTypes = array(
		'#' => array('Header'),
		'*' => array('Rule', 'List'),
		'+' => array('List'),
		'-' => array('SetextHeader', 'Table', 'Rule', 'List'),
		'0' => array('List'),
		'1' => array('List'),
		'2' => array('List'),
		'3' => array('List'),
		'4' => array('List'),
		'5' => array('List'),
		'6' => array('List'),
		'7' => array('List'),
		'8' => array('List'),
		'9' => array('List'),
		':' => array('Table'),
		'<' => array('Comment', 'Markup'),
		'=' => array('SetextHeader'),
		'>' => array('Quote'),
		'[' => array('Reference'),
		'_' => array('Rule'),
		'`' => array('FencedCode'),
		'|' => array('Table'),
		'~' => array('FencedCode'),
	);
	protected $unmarkedBlockTypes = array(
		'Code',
	);


	protected $inlineMarkerList = '!*_&[:<`~\\';
	protected $InlineTypes = array(
		'!' => array('Image'),
		'&' => array('SpecialCharacter'),
		'*' => array('Emphasis'),
		':' => array('Url'),
		'<' => array('UrlTag', 'EmailTag', 'Markup'),
		'[' => array('Link'),
		// '_' => array('Emphasis'),
		'`' => array('Code'),
		'~' => array('Strikethrough'),
		'\\' => array('EscapeSequence'),
	);


	protected $LiturgicalInlineTypes = array(
		'[' => array(
			'LiturgicalCross',	// [+] This will insert the symbol to prompt the reader to cross themselves. Rendered as ✛ in non HTML [U+271B or `&#10011;`].
			'LiturgicalMidpoint',	// [*] This is the for denoting a mid-point in chanted texts.
			'LiturgicalDagger',	// [t] This is the dagger/obelisk that indicates the current line continues below. Helpful with chanted texts with more than two lines. Rendered as † in non HTML [U+2020 or `&#8224;` or `&dagger;`].
			'TextRed',	// [red]Text[/red]
		),
		'‾' => array('OverUnderLine'),
		'_' => array('OverUnderLine'),
	);
	protected $LiturgicalBlockTypes = array(
		'[V]' => array('Versicle'),	// During the *Responsory* it denotes a **Versicle** line with the leader speaking. Rendered as ℣ in non HTML [U+2123 or `&#8483;`].
		'[R]' => array('Response'),	// During the *Responsory* it denotes **Response** line with all speaking. Rendered as ℟ in non HTML [U+211F or `&#8479;`].
		'[II]' => array('IntercessionIntro'),	// During the *Intercessions* this indicates the **Introduction** to the intentions. When prayed in a group it should be read only by the leader.
		'[IR]' => array('IntercessionResponse'),	// During the *Intercessions* this indicated the **Response**. It should only be placed in the source text on the line after the introduction. It will be placed in other locations when formated. When prayed in a group it should be read only by the leader.
		'[I1]' => array('IntentionPart1'),	// During the *Intercessions* this indicates the first part of an **intention**.
		'[I2]' => array('IntentionPart2'),	// During the *Intercessions* this indicates the second part of an **intention**.
	);
	/*
	 *	END: Establish the variables
	 */



	/*
	 * Configuration Setters
	 */
	public function setBreaksEnabled(bool $breaksEnabled){
		$this->breaksEnabled = $breaksEnabled;

		return $this;
	}

	public function setMarkupEscaped(bool $markupEscaped){
		$this->markupEscaped = $markupEscaped;

		return $this;
	}

	public function setLiturgicalElements(bool $liturgicalElements){
		$this->liturgicalElements = $liturgicalElements;

		return $this;
	}

	public function setLiturgicalHTML(bool $liturgicalHTML){
		$this->liturgicalHTML = $liturgicalHTML;

		return $this;
	}

	public function setSupressAlleluia(bool $supressAlleluia){
		$this->supressAlleluia = $supressAlleluia;

		return $this;
	}

	public function setFootnotesEnabled(bool $footnotesEnabled){
		$this->footnotesEnabled = $footnotesEnabled;

		return $this;
	}
	/*
	 * END: Configuration Setters
	 */




	/**
	 * Break the supplied text into lines
	 *
	 * @param  string		$text	The text object we need to convert into lines
	 * @return array
	 */
	protected function textElements($text){
		# make sure no definitions are set
		$this->DefinitionData = array();

		# standardize line breaks
		$text = str_replace(array("\r\n", "\r"), "\n", $text);

		# remove surrounding line breaks
		$text = trim($text, "\n");

		# split text into lines
		$lines = explode("\n", $text);

		# iterate through lines to identify blocks
		return $this->linesElements($lines);
	}


	/**
	 * Break the supplied text into lines
	 *
	 * @param  array		$lines	array of lines that need to be procesed
	 * @return array
	 */
	protected function lines(array $lines){
		return $this->elements($this->linesElements($lines));
	}


	/**
	 * Break the supplied text into lines
	 *
	 * @param  array		$lines	array of lines that need to be procesed
	 * @return array
	 */
	protected function linesElements(array $lines){
		$Elements = array();
		$CurrentBlock = null;

		foreach ($lines as $line){
			if (chop($line) === ''){
				if (isset($CurrentBlock)){
					$CurrentBlock['interrupted'] = (isset($CurrentBlock['interrupted'])
						? $CurrentBlock['interrupted'] + 1 : 1
					);
				}

				continue;
			}

			while (($beforeTab = strstr($line, "\t", true)) !== false){
				$shortage = 4 - mb_strlen($beforeTab, 'utf-8') % 4;

				$line = $beforeTab
					. str_repeat(' ', $shortage)
					. substr($line, strlen($beforeTab) + 1);
			}

			$indent = strspn($line, ' ');

			$text = $indent > 0 ? substr($line, $indent) : $line;

			# ~

			$Line = array('body' => $line, 'indent' => $indent, 'text' => $text);

			# ~

			if (isset($CurrentBlock['continuable'])){
				$methodName = 'block' . $CurrentBlock['type'] . 'Continue';
				$Block = $this->$methodName($Line, $CurrentBlock);

				if (isset($Block)){
					$CurrentBlock = $Block;

					continue;
				}
				else if ($this->isBlockCompletable($CurrentBlock['type'])){
					$methodName = 'block' . $CurrentBlock['type'] . 'Complete';
					$CurrentBlock = $this->$methodName($CurrentBlock);
				}
			}

			# ~

			$marker = $text[0];

			var_dump( $marker );


			/*
			 * Prepare the unmarked block types
			 */
			$blockTypes = $this->unmarkedBlockTypes;

			if (isset($this->BlockTypes[$marker])){
				foreach ($this->BlockTypes[$marker] as $blockType){
					$blockTypes []= $blockType;
				}
			}

			var_dump( $blockTypes );

			/*
			 * Prepare the unmarked block types
			 */
			foreach ($blockTypes as $blockType){
				$Block = $this->{"block$blockType"}($Line, $CurrentBlock);

				if (isset($Block)){
					$Block['type'] = $blockType;

					if ( ! isset($Block['identified'])){
						if (isset($CurrentBlock)){
							$Elements[] = $this->extractElement($CurrentBlock);
						}

						$Block['identified'] = true;
					}

					if ($this->isBlockContinuable($blockType)){
						$Block['continuable'] = true;
					}

					$CurrentBlock = $Block;

					continue 2;
				}
			}

			# ~

			if (isset($CurrentBlock) and $CurrentBlock['type'] === 'Paragraph'){
				$Block = $this->paragraphContinue($Line, $CurrentBlock);
			}

			if (isset($Block)){
				$CurrentBlock = $Block;
			}
			else{
				if (isset($CurrentBlock)){
					$Elements[] = $this->extractElement($CurrentBlock);
				}

				$CurrentBlock = $this->paragraph($Line);

				$CurrentBlock['identified'] = true;
			}
		} // End: foreach($lines)

		# ~

		if (isset($CurrentBlock['continuable']) and $this->isBlockCompletable($CurrentBlock['type'])){
			$methodName = 'block' . $CurrentBlock['type'] . 'Complete';
			$CurrentBlock = $this->$methodName($CurrentBlock);
		}

		# ~

		if (isset($CurrentBlock)){
			$Elements[] = $this->extractElement($CurrentBlock);
			var_dump( $Elements );
		}

		# ~

		return $Elements;
	}



	protected function extractElement(array $Component){
		if ( ! isset($Component['element'])){
			if (isset($Component['markup'])){
				$Component['element'] = array('rawHtml' => $Component['markup']);
			}
			elseif (isset($Component['hidden'])){
				$Component['element'] = array();
			}
		}

		return $Component['element'];
	}

	protected function isBlockContinuable($Type){
		return method_exists($this, 'block' . $Type . 'Continue');
	}

	protected function isBlockCompletable($Type){
		return method_exists($this, 'block' . $Type . 'Complete');
	}





	/*
	 * Block Element Processing
	 */
    protected function blockCode($Line, $Block = null)
    {
        if (isset($Block) and $Block['type'] === 'Paragraph' and ! isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['indent'] >= 4)
        {
            $text = substr($Line['body'], 4);

            $Block = array(
                'element' => array(
                    'name' => 'pre',
                    'element' => array(
                        'name' => 'code',
                        'text' => $text,
                    ),
                ),
            );

            return $Block;
        }
    }

    protected function blockCodeContinue($Line, $Block)
    {
        if ($Line['indent'] >= 4)
        {
            if (isset($Block['interrupted']))
            {
                $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

                unset($Block['interrupted']);
            }

            $Block['element']['element']['text'] .= "\n";

            $text = substr($Line['body'], 4);

            $Block['element']['element']['text'] .= $text;

            return $Block;
        }
    }

    protected function blockCodeComplete($Block)
    {
        return $Block;
    }

    #
    # Comment

    protected function blockComment($Line)
    {
        if ($this->markupEscaped or $this->safeMode)
        {
            return;
        }

        if (strpos($Line['text'], '<!--') === 0)
        {
            $Block = array(
                'element' => array(
                    'rawHtml' => $Line['body'],
                    'autobreak' => true,
                ),
            );

            if (strpos($Line['text'], '-->') !== false)
            {
                $Block['closed'] = true;
            }

            return $Block;
        }
    }

    protected function blockCommentContinue($Line, array $Block)
    {
        if (isset($Block['closed']))
        {
            return;
        }

        $Block['element']['rawHtml'] .= "\n" . $Line['body'];

        if (strpos($Line['text'], '-->') !== false)
        {
            $Block['closed'] = true;
        }

        return $Block;
    }

    #
    # Fenced Code

    protected function blockFencedCode($Line)
    {
        $marker = $Line['text'][0];

        $openerLength = strspn($Line['text'], $marker);

        if ($openerLength < 3)
        {
            return;
        }

        $infostring = trim(substr($Line['text'], $openerLength), "\t ");

        if (strpos($infostring, '`') !== false)
        {
            return;
        }

        $Element = array(
            'name' => 'code',
            'text' => '',
        );

        if ($infostring !== ''){
            /**
             * https://www.w3.org/TR/2011/WD-html5-20110525/elements.html#classes
             * Every HTML element may have a class attribute specified.
             * The attribute, if specified, must have a value that is a set
             * of space-separated tokens representing the various classes
             * that the element belongs to.
             * [...]
             * The space characters, for the purposes of this specification,
             * are U+0020 SPACE, U+0009 CHARACTER TABULATION (tab),
             * U+000A LINE FEED (LF), U+000C FORM FEED (FF), and
             * U+000D CARRIAGE RETURN (CR).
             */
            $language = substr($infostring, 0, strcspn($infostring, " \t\n\f\r"));

            $Element['attributes'] = array('class' => "language-$language");
        }

        $Block = array(
            'char' => $marker,
            'openerLength' => $openerLength,
            'element' => array(
                'name' => 'pre',
                'element' => $Element,
            ),
        );

        return $Block;
    }

    protected function blockFencedCodeContinue($Line, $Block)
    {
        if (isset($Block['complete']))
        {
            return;
        }

        if (isset($Block['interrupted']))
        {
            $Block['element']['element']['text'] .= str_repeat("\n", $Block['interrupted']);

            unset($Block['interrupted']);
        }

        if (($len = strspn($Line['text'], $Block['char'])) >= $Block['openerLength']
            and chop(substr($Line['text'], $len), ' ') === ''
        ) {
            $Block['element']['element']['text'] = substr($Block['element']['element']['text'], 1);

            $Block['complete'] = true;

            return $Block;
        }

        $Block['element']['element']['text'] .= "\n" . $Line['body'];

        return $Block;
    }

    protected function blockFencedCodeComplete($Block)
    {
        return $Block;
    }

    #
    # Header

    protected function blockHeader($Line)
    {
        $level = strspn($Line['text'], '#');

        if ($level > 6)
        {
            return;
        }

        $text = trim($Line['text'], '#');

        if ($this->strictMode and isset($text[0]) and $text[0] !== ' ')
        {
            return;
        }

        $text = trim($text, ' ');

        $Block = array(
            'element' => array(
                'name' => 'h' . $level,
                'handler' => array(
                    'function' => 'lineElements',
                    'argument' => $text,
                    'destination' => 'elements',
                )
            ),
        );

        return $Block;
    }

    #
    # List

    protected function blockList($Line, array $CurrentBlock = null)
    {
        list($name, $pattern) = $Line['text'][0] <= '-' ? array('ul', '[*+-]') : array('ol', '[0-9]{1,9}+[.\)]');

        if (preg_match('/^('.$pattern.'([ ]++|$))(.*+)/', $Line['text'], $matches))
        {
            $contentIndent = strlen($matches[2]);

            if ($contentIndent >= 5)
            {
                $contentIndent -= 1;
                $matches[1] = substr($matches[1], 0, -$contentIndent);
                $matches[3] = str_repeat(' ', $contentIndent) . $matches[3];
            }
            elseif ($contentIndent === 0)
            {
                $matches[1] .= ' ';
            }

            $markerWithoutWhitespace = strstr($matches[1], ' ', true);

            $Block = array(
                'indent' => $Line['indent'],
                'pattern' => $pattern,
                'data' => array(
                    'type' => $name,
                    'marker' => $matches[1],
                    'markerType' => ($name === 'ul' ? $markerWithoutWhitespace : substr($markerWithoutWhitespace, -1)),
                ),
                'element' => array(
                    'name' => $name,
                    'elements' => array(),
                ),
            );
            $Block['data']['markerTypeRegex'] = preg_quote($Block['data']['markerType'], '/');

            if ($name === 'ol')
            {
                $listStart = ltrim(strstr($matches[1], $Block['data']['markerType'], true), '0') ?: '0';

                if ($listStart !== '1')
                {
                    if (
                        isset($CurrentBlock)
                        and $CurrentBlock['type'] === 'Paragraph'
                        and ! isset($CurrentBlock['interrupted'])
                    ) {
                        return;
                    }

                    $Block['element']['attributes'] = array('start' => $listStart);
                }
            }

            $Block['li'] = array(
                'name' => 'li',
                'handler' => array(
                    'function' => 'li',
                    'argument' => !empty($matches[3]) ? array($matches[3]) : array(),
                    'destination' => 'elements'
                )
            );

            $Block['element']['elements'] []= & $Block['li'];

            return $Block;
        }
    }

    protected function blockListContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']) and empty($Block['li']['handler']['argument']))
        {
            return null;
        }

        $requiredIndent = ($Block['indent'] + strlen($Block['data']['marker']));

        if ($Line['indent'] < $requiredIndent
            and (
                (
                    $Block['data']['type'] === 'ol'
                    and preg_match('/^[0-9]++'.$Block['data']['markerTypeRegex'].'(?:[ ]++(.*)|$)/', $Line['text'], $matches)
                ) or (
                    $Block['data']['type'] === 'ul'
                    and preg_match('/^'.$Block['data']['markerTypeRegex'].'(?:[ ]++(.*)|$)/', $Line['text'], $matches)
                )
            )
        ) {
            if (isset($Block['interrupted']))
            {
                $Block['li']['handler']['argument'] []= '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            unset($Block['li']);

            $text = isset($matches[1]) ? $matches[1] : '';

            $Block['indent'] = $Line['indent'];

            $Block['li'] = array(
                'name' => 'li',
                'handler' => array(
                    'function' => 'li',
                    'argument' => array($text),
                    'destination' => 'elements'
                )
            );

            $Block['element']['elements'] []= & $Block['li'];

            return $Block;
        }
        elseif ($Line['indent'] < $requiredIndent and $this->blockList($Line))
        {
            return null;
        }

        if ($Line['text'][0] === '[' and $this->blockReference($Line))
        {
            return $Block;
        }

        if ($Line['indent'] >= $requiredIndent)
        {
            if (isset($Block['interrupted']))
            {
                $Block['li']['handler']['argument'] []= '';

                $Block['loose'] = true;

                unset($Block['interrupted']);
            }

            $text = substr($Line['body'], $requiredIndent);

            $Block['li']['handler']['argument'] []= $text;

            return $Block;
        }

        if ( ! isset($Block['interrupted']))
        {
            $text = preg_replace('/^[ ]{0,'.$requiredIndent.'}+/', '', $Line['body']);

            $Block['li']['handler']['argument'] []= $text;

            return $Block;
        }
    }

    protected function blockListComplete(array $Block)
    {
        if (isset($Block['loose']))
        {
            foreach ($Block['element']['elements'] as &$li)
            {
                if (end($li['handler']['argument']) !== '')
                {
                    $li['handler']['argument'] []= '';
                }
            }
        }

        return $Block;
    }

    #
    # Quote

    protected function blockQuote($Line)
    {
        if (preg_match('/^>[ ]?+(.*+)/', $Line['text'], $matches))
        {
            $Block = array(
                'element' => array(
                    'name' => 'blockquote',
                    'handler' => array(
                        'function' => 'linesElements',
                        'argument' => (array) $matches[1],
                        'destination' => 'elements',
                    )
                ),
            );

            return $Block;
        }
    }

    protected function blockQuoteContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['text'][0] === '>' and preg_match('/^>[ ]?+(.*+)/', $Line['text'], $matches))
        {
            $Block['element']['handler']['argument'] []= $matches[1];

            return $Block;
        }

        if ( ! isset($Block['interrupted']))
        {
            $Block['element']['handler']['argument'] []= $Line['text'];

            return $Block;
        }
    }

    #
    # Rule

    protected function blockRule($Line)
    {
        $marker = $Line['text'][0];

        if (substr_count($Line['text'], $marker) >= 3 and chop($Line['text'], " $marker") === '')
        {
            $Block = array(
                'element' => array(
                    'name' => 'hr',
                ),
            );

            return $Block;
        }
    }

    #
    # Setext

    protected function blockSetextHeader($Line, array $Block = null)
    {
        if ( ! isset($Block) or $Block['type'] !== 'Paragraph' or isset($Block['interrupted']))
        {
            return;
        }

        if ($Line['indent'] < 4 and chop(chop($Line['text'], ' '), $Line['text'][0]) === '')
        {
            $Block['element']['name'] = $Line['text'][0] === '=' ? 'h1' : 'h2';

            return $Block;
        }
    }

    #
    # Markup

    protected function blockMarkup($Line)
    {
        if ($this->markupEscaped or $this->safeMode)
        {
            return;
        }

        if (preg_match('/^<[\/]?+(\w*)(?:[ ]*+'.$this->regexHtmlAttribute.')*+[ ]*+(\/)?>/', $Line['text'], $matches))
        {
            $element = strtolower($matches[1]);

            if (in_array($element, $this->textLevelElements))
            {
                return;
            }

            $Block = array(
                'name' => $matches[1],
                'element' => array(
                    'rawHtml' => $Line['text'],
                    'autobreak' => true,
                ),
            );

            return $Block;
        }
    }

    protected function blockMarkupContinue($Line, array $Block)
    {
        if (isset($Block['closed']) or isset($Block['interrupted']))
        {
            return;
        }

        $Block['element']['rawHtml'] .= "\n" . $Line['body'];

        return $Block;
    }

    #
    # Reference

    protected function blockReference($Line)
    {
        if (strpos($Line['text'], ']') !== false
            and preg_match('/^\[(.+?)\]:[ ]*+<?(\S+?)>?(?:[ ]+["\'(](.+)["\')])?[ ]*+$/', $Line['text'], $matches)
        ) {
            $id = strtolower($matches[1]);

            $Data = array(
                'url' => $matches[2],
                'title' => isset($matches[3]) ? $matches[3] : null,
            );

            $this->DefinitionData['Reference'][$id] = $Data;

            $Block = array(
                'element' => array(),
            );

            return $Block;
        }
    }

    #
    # Table

    protected function blockTable($Line, array $Block = null)
    {
        if ( ! isset($Block) or $Block['type'] !== 'Paragraph' or isset($Block['interrupted']))
        {
            return;
        }

        if (
            strpos($Block['element']['handler']['argument'], '|') === false
            and strpos($Line['text'], '|') === false
            and strpos($Line['text'], ':') === false
            or strpos($Block['element']['handler']['argument'], "\n") !== false
        ) {
            return;
        }

        if (chop($Line['text'], ' -:|') !== '')
        {
            return;
        }

        $alignments = array();

        $divider = $Line['text'];

        $divider = trim($divider);
        $divider = trim($divider, '|');

        $dividerCells = explode('|', $divider);

        foreach ($dividerCells as $dividerCell)
        {
            $dividerCell = trim($dividerCell);

            if ($dividerCell === '')
            {
                return;
            }

            $alignment = null;

            if ($dividerCell[0] === ':')
            {
                $alignment = 'left';
            }

            if (substr($dividerCell, - 1) === ':')
            {
                $alignment = $alignment === 'left' ? 'center' : 'right';
            }

            $alignments []= $alignment;
        }

        # ~

        $HeaderElements = array();

        $header = $Block['element']['handler']['argument'];

        $header = trim($header);
        $header = trim($header, '|');

        $headerCells = explode('|', $header);

        if (count($headerCells) !== count($alignments))
        {
            return;
        }

        foreach ($headerCells as $index => $headerCell)
        {
            $headerCell = trim($headerCell);

            $HeaderElement = array(
                'name' => 'th',
                'handler' => array(
                    'function' => 'lineElements',
                    'argument' => $headerCell,
                    'destination' => 'elements',
                )
            );

            if (isset($alignments[$index]))
            {
                $alignment = $alignments[$index];

                $HeaderElement['attributes'] = array(
                    'style' => "text-align: $alignment;",
                );
            }

            $HeaderElements []= $HeaderElement;
        }

        # ~

        $Block = array(
            'alignments' => $alignments,
            'identified' => true,
            'element' => array(
                'name' => 'table',
                'elements' => array(),
            ),
        );

        $Block['element']['elements'] []= array(
            'name' => 'thead',
        );

        $Block['element']['elements'] []= array(
            'name' => 'tbody',
            'elements' => array(),
        );

        $Block['element']['elements'][0]['elements'] []= array(
            'name' => 'tr',
            'elements' => $HeaderElements,
        );

        return $Block;
    }

    protected function blockTableContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']))
        {
            return;
        }

        if (count($Block['alignments']) === 1 or $Line['text'][0] === '|' or strpos($Line['text'], '|'))
        {
            $Elements = array();

            $row = $Line['text'];

            $row = trim($row);
            $row = trim($row, '|');

            preg_match_all('/(?:(\\\\[|])|[^|`]|`[^`]++`|`)++/', $row, $matches);

            $cells = array_slice($matches[0], 0, count($Block['alignments']));

            foreach ($cells as $index => $cell)
            {
                $cell = trim($cell);

                $Element = array(
                    'name' => 'td',
                    'handler' => array(
                        'function' => 'lineElements',
                        'argument' => $cell,
                        'destination' => 'elements',
                    )
                );

                if (isset($Block['alignments'][$index]))
                {
                    $Element['attributes'] = array(
                        'style' => 'text-align: ' . $Block['alignments'][$index] . ';',
                    );
                }

                $Elements []= $Element;
            }

            $Element = array(
                'name' => 'tr',
                'elements' => $Elements,
            );

            $Block['element']['elements'][1]['elements'] []= $Element;

            return $Block;
        }
    }

    #
    # ~
    #

    protected function paragraph($Line)
    {
        return array(
            'type' => 'Paragraph',
            'element' => array(
                'name' => 'p',
                'handler' => array(
                    'function' => 'lineElements',
                    'argument' => $Line['text'],
                    'destination' => 'elements',
                ),
            ),
        );
    }

    protected function paragraphContinue($Line, array $Block)
    {
        if (isset($Block['interrupted']))
        {
            return;
        }

        $Block['element']['handler']['argument'] .= "\n".$Line['text'];

        return $Block;
    }
	/*
	 * END: Block Element Processing
	 */




	/*
	 * Inline Element Processing
	 */
	protected function lineElements($text, $nonNestables = array()){
		# standardize line breaks
		$text = str_replace(array("\r\n", "\r"), "\n", $text);

		$Elements = array();
		$ActiveInlineTypes = $this->InlineTypes;
		if( $this->liturgicalElements ){
			// Include Liturgiacl inline elements
			foreach( $this->LiturgicalInlineTypes as $k => $v ){
				if( array_key_exists( $k, $ActiveInlineTypes ) ){
					$ActiveInlineTypes[$k] = array_merge( $ActiveInlineTypes[$k], $v );
				}else{
					$ActiveInlineTypes[$k] = $v;
				}
			}
		}
// var_dump( $ActiveInlineTypes );

		$nonNestables = (empty($nonNestables)
			? array()
			: array_combine($nonNestables, $nonNestables)
		);

		# $excerpt is based on the first occurrence of a marker
		while ($excerpt = strpbrk($text, $this->inlineMarkerList)){
			$marker = mb_substr( $excerpt, 0, 1 );
			$markerPosition = strlen($text) - strlen($excerpt);

			$Excerpt = array('text' => $excerpt, 'context' => $text);

			foreach($ActiveInlineTypes[$marker] as $inlineType){
				# check to see if the current inline type is nestable in the current context

				if (isset($nonNestables[$inlineType])){
					continue;
				}

				$Inline = $this->{"inline$inlineType"}($Excerpt);

				if ( ! isset($Inline)){
					continue;
				}

				# makes sure that the inline belongs to "our" marker

				if (isset($Inline['position']) and $Inline['position'] > $markerPosition){
					continue;
				}

				# sets a default inline position

				if ( ! isset($Inline['position'])){
					$Inline['position'] = $markerPosition;
				}

				# cause the new element to 'inherit' our non nestables


				$Inline['element']['nonNestables'] = isset($Inline['element']['nonNestables'])
				? array_merge($Inline['element']['nonNestables'], $nonNestables)
				: $nonNestables
				;

				# the text that comes before the inline
				$unmarkedText = substr($text, 0, $Inline['position']);

				# compile the unmarked text
				$InlineText = $this->inlineText($unmarkedText);
				$Elements[] = $InlineText['element'];

				# compile the inline
				$Elements[] = $this->extractElement($Inline);

				# remove the examined text
				$text = substr($text, $Inline['position'] + $Inline['extent']);

				continue 2;
			}

			# the marker does not belong to an inline

			$unmarkedText = substr($text, 0, $markerPosition + 1);

			$InlineText = $this->inlineText($unmarkedText);
			$Elements[] = $InlineText['element'];

			$text = substr($text, $markerPosition + 1);
		}

		$InlineText = $this->inlineText($text);
		$Elements[] = $InlineText['element'];

		foreach ($Elements as &$Element){
			if ( ! isset($Element['autobreak'])){
				$Element['autobreak'] = false;
			}
		}

		return $Elements;
	}

    #
    # ~
    #

	protected function inlineText($text){
		$Inline = array(
			'extent' => strlen($text),
			'element' => array(),
		);

		$Inline['element']['elements'] = self::pregReplaceElements(
			$this->breaksEnabled ? '/[ ]*+\n/' : '/(?:[ ]*+\\\\|[ ]{2,}+)\n/',
			array(
				array('name' => 'br'),
				array('text' => "\n"),
			),
			$text
		);

		return $Inline;
	}

	protected function inlineCode($Excerpt){
		$marker = $Excerpt['text'][0];

		if (preg_match('/^(['.$marker.']++)[ ]*+(.+?)[ ]*+(?<!['.$marker.'])\1(?!'.$marker.')/s', $Excerpt['text'], $matches)){
			$text = $matches[2];
			$text = preg_replace('/[ ]*+\n/', ' ', $text);

			return array(
				'extent' => strlen($matches[0]),
				'element' => array(
					'name' => 'code',
					'text' => $text,
				),
			);
		}
	}

	protected function inlineEmailTag($Excerpt){
		$hostnameLabel = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';

		$commonMarkEmail = '[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]++@' . $hostnameLabel . '(?:\.' . $hostnameLabel . ')*';

		if (strpos($Excerpt['text'], '>') !== false && preg_match("/^<((mailto:)?$commonMarkEmail)>/i", $Excerpt['text'], $matches ) ){
			$url = $matches[1];

			if ( ! isset($matches[2])){
				$url = "mailto:$url";
			}

			return array(
				'extent' => strlen($matches[0]),
				'element' => array(
					'name' => 'a',
					'text' => $matches[1],
					'attributes' => array(
						'href' => $url,
					),
				),
			);
		}
	}

	protected function inlineEmphasis($Excerpt){
		if ( ! isset($Excerpt['text'][1])){
			return;
		}

		$marker = $Excerpt['text'][0];

		if ($Excerpt['text'][1] === $marker and preg_match($this->StrongRegex[$marker], $Excerpt['text'], $matches)){
			$emphasis = 'strong';
		}
		elseif (preg_match($this->EmRegex[$marker], $Excerpt['text'], $matches)){
			$emphasis = 'em';
		}else{
			return;
		}

		return array(
			'extent' => strlen($matches[0]),
			'element' => array(
				'name' => $emphasis,
				'handler' => array(
					'function' => 'lineElements',
					'argument' => $matches[1],
					'destination' => 'elements',
				)
			),
		);
	}

	protected function inlineEscapeSequence($Excerpt){
		if (isset($Excerpt['text'][1]) and in_array($Excerpt['text'][1], $this->specialCharacters)){
			return array(
				'element' => array('rawHtml' => $Excerpt['text'][1]),
				'extent' => 2,
			);
		}
	}

	protected function inlineImage($Excerpt){
		if ( ! isset($Excerpt['text'][1]) or $Excerpt['text'][1] !== '['){
			return;
		}

		$Excerpt['text']= substr($Excerpt['text'], 1);

		$Link = $this->inlineLink($Excerpt);

		if ($Link === null){
			return;
		}

		$Inline = array(
			'extent' => $Link['extent'] + 1,
			'element' => array(
				'name' => 'img',
				'attributes' => array(
					'src' => $Link['element']['attributes']['href'],
					'alt' => $Link['element']['handler']['argument'],
				),
				'autobreak' => true,
			),
		);

		$Inline['element']['attributes'] += $Link['element']['attributes'];

		unset($Inline['element']['attributes']['href']);

		return $Inline;
	}

	protected function inlineLink($Excerpt){
		$Element = array(
			'name' => 'a',
			'handler' => array(
				'function' => 'lineElements',
				'argument' => null,
				'destination' => 'elements',
			),
			'nonNestables' => array('Url', 'Link'),
			'attributes' => array(
				'href' => null,
				'title' => null,
			),
		);

		$extent = 0;

		$remainder = $Excerpt['text'];

		if (preg_match('/\[((?:[^][]++|(?R))*+)\]/', $remainder, $matches)){
			$Element['handler']['argument'] = $matches[1];

			$extent += strlen($matches[0]);

			$remainder = substr($remainder, $extent);
		}else{
			return;
		}

		if (preg_match('/^[(]\s*+((?:[^ ()]++|[(][^ )]+[)])++)(?:[ ]+("[^"]*+"|\'[^\']*+\'))?\s*+[)]/', $remainder, $matches)){
			$Element['attributes']['href'] = $matches[1];

			if (isset($matches[2])){
				$Element['attributes']['title'] = substr($matches[2], 1, - 1);
			}

			$extent += strlen($matches[0]);
		}else{
			if (preg_match('/^\s*\[(.*?)\]/', $remainder, $matches)){
				$definition = strlen($matches[1]) ? $matches[1] : $Element['handler']['argument'];
				$definition = strtolower($definition);

				$extent += strlen($matches[0]);
			}else{
				$definition = strtolower($Element['handler']['argument']);
			}

			if ( ! isset($this->DefinitionData['Reference'][$definition])){
				return;
			}

			$Definition = $this->DefinitionData['Reference'][$definition];

			$Element['attributes']['href'] = $Definition['url'];
			$Element['attributes']['title'] = $Definition['title'];
		}

		return array(
			'extent' => $extent,
			'element' => $Element,
		);
	}

	protected function inlineMarkup($Excerpt){
		if( $this->markupEscaped || $this->safeMode || strpos($Excerpt['text'], '>') === false){
			return;
		}

		if( $Excerpt['text'][1] === '/' && preg_match('/^<\/\w[\w-]*+[ ]*+>/s', $Excerpt['text'], $matches)){
			return array(
				'element' => array('rawHtml' => $matches[0]),
				'extent' => strlen($matches[0]),
			);
		}

		if( $Excerpt['text'][1] === '!' && preg_match('/^<!---?[^>-](?:-?+[^-])*-->/s', $Excerpt['text'], $matches)){
			return array(
				'element' => array('rawHtml' => $matches[0]),
				'extent' => strlen($matches[0]),
			);
		}

		if( $Excerpt['text'][1] !== ' ' && preg_match('/^<\w[\w-]*+(?:[ ]*+'.$this->regexHtmlAttribute.')*+[ ]*+\/?>/s', $Excerpt['text'], $matches)){
			return array(
				'element' => array('rawHtml' => $matches[0]),
				'extent' => strlen($matches[0]),
			);
		}
	}

	protected function inlineSpecialCharacter($Excerpt){
		if (substr($Excerpt['text'], 1, 1) !== ' ' && strpos($Excerpt['text'], ';') !== false && preg_match('/^&(#?+[0-9a-zA-Z]++);/', $Excerpt['text'], $matches) ){
			return array(
				'element' => array('rawHtml' => '&' . $matches[1] . ';'),
				'extent' => strlen($matches[0]),
			);
		}

		return;
	}

	protected function inlineStrikethrough($Excerpt){
		if ( ! isset($Excerpt['text'][1])){
			return;
		}

		if ($Excerpt['text'][1] === '~' and preg_match('/^~~(?=\S)(.+?)(?<=\S)~~/', $Excerpt['text'], $matches)){
			return array(
				'extent' => strlen($matches[0]),
				'element' => array(
					'name' => 'del',
					'handler' => array(
						'function' => 'lineElements',
						'argument' => $matches[1],
						'destination' => 'elements',
					)
				),
			);
		}
	}

	protected function inlineUrl($Excerpt){
		if ($this->urlsLinked !== true or ! isset($Excerpt['text'][2]) or $Excerpt['text'][2] !== '/'){
			return;
		}

		if (strpos($Excerpt['context'], 'http') !== false && preg_match('/\bhttps?+:[\/]{2}[^\s<]+\b\/*+/ui', $Excerpt['context'], $matches, PREG_OFFSET_CAPTURE)){
			$url = $matches[0][0];

			$Inline = array(
				'extent' => strlen($matches[0][0]),
				'position' => $matches[0][1],
				'element' => array(
					'name' => 'a',
					'text' => $url,
					'attributes' => array(
						'href' => $url,
					),
				),
			);

			return $Inline;
		}
	}

	protected function inlineUrlTag($Excerpt){
		if (strpos($Excerpt['text'], '>') !== false && preg_match('/^<(\w++:\/{2}[^ >]++)>/i', $Excerpt['text'], $matches)){
			$url = $matches[1];

			return array(
				'extent' => strlen($matches[0]),
				'element' => array(
					'name' => 'a',
					'text' => $url,
					'attributes' => array(
						'href' => $url,
					),
				),
			);
		}
	}

    # ~

	protected function unmarkedText($text){
		$Inline = $this->inlineText($text);
		return $this->element($Inline['element']);
	}
	/*
	 * END: Inline Element Processing
	 */





	/*
	 * Liturgical Element Processing
	 */
	protected function inlineLiturgicalCross($Excerpt){
		$Element = array(
			'rawHtml' => "&#10011;",
			'allowRawHtmlInSafeMode' => true,
		);
		if( $this->liturgicalHTML ){
			$Element['name'] = 'span';
			$Element['attributes'] = array(
					'class' => 'symbol-cross',
				);
		}


		$extent = 0;
		$remainder = $Excerpt['text'];

		if( preg_match('/\[\+\]/', $remainder, $matches) ){
			$extent += strlen($matches[0]);
			$remainder = substr($remainder, $extent);
		}else{
			return;
		}


		return array(
			'extent' => $extent,
			'element' => $Element,
		);
	}
	
	protected function inlineLiturgicalMidpoint( $Excerpt ){
		$Element = array(
			'rawHtml' => "*",
			// 'allowRawHtmlInSafeMode' => true,
		);
		if( $this->liturgicalHTML ){
			$Element['name'] = 'span';
			$Element['attributes'] = array(
					'class' => 'symbol-star',
				);
		}

		$extent = 0;
		$remainder = $Excerpt['text'];

		if( preg_match('/\[\*\]/', $remainder, $matches) ){
			$extent += strlen($matches[0]);
			$remainder = substr($remainder, $extent);
		}else{
			return;
		}


		return array(
			'extent' => $extent,
			'element' => $Element,
		);
	}

	protected function inlineLiturgicalDagger( $Excerpt ){
		$Element = array(
			'rawHtml' => "&#8224;",
			'allowRawHtmlInSafeMode' => true,
		);
		if( $this->liturgicalHTML ){
			$Element['name'] = 'span';
			$Element['attributes'] = array(
					'class' => 'symbol-dagger',
				);
		}

		$extent = 0;
		$remainder = $Excerpt['text'];

		if( preg_match('/\[t\]/', $remainder, $matches) ){
			$extent += strlen($matches[0]);
			$remainder = substr($remainder, $extent);
		}else{
			return;
		}


		return array(
			'extent' => $extent,
			'element' => $Element,
		);
	}

	protected function inlineTextRed( $Excerpt ){
		if( $this->liturgicalHTML ){
			$Element = array(
				'name' => 'span',
				'attributes' => array(
						'class' => 'color-red',
					),
			);
		}else{
			$Element = array();	// This will simply strip the tags from rendering - as there is no direct way to render red text
		}

		$extent = 0;
		$remainder = $Excerpt['text'];

		if( preg_match('/^\[red\]((.|\n)*?)\[\/red\]/', $remainder, $matches) ){
			$extent += strlen($matches[0]);
			$remainder = substr($remainder, $extent);
			$Element['text'] = $matches[1];
		}else{
			return;
		}


		return array(
			'extent' => $extent,
			'element' => $Element,
		);
	}

	protected function inlineOverUnderLine( $Excerpt ){
		if( $this->liturgicalHTML ){
			$Element = array(
				'name' => 'span',
				'attributes' => array(
						'class' => null
					),
			);
		}else{
			$Element = array();	// This will simply strip the tags from rendering - as there is no direct way to render red text
		}

		$extent = 0;
		$remainder = $Excerpt['text'];

		if( preg_match('/^_‾|‾_((.|\n)*?)_‾|‾_/u', $remainder, $matches) ){
			$extent += strlen($matches[0]);
			$remainder = substr($remainder, $extent);
			$Element['text'] = $matches[1];
			$Element['attributes']['class'] = 'text-overline text-underline';
		}else if( preg_match('/^‾((.|\n)*?)‾/u', $remainder, $matches) ){
			$extent += strlen($matches[0]);
			$remainder = substr($remainder, $extent);
			$Element['text'] = $matches[1];
			$Element['attributes']['class'] = 'text-overline';

		}else if( preg_match('/^_((.|\n)*?)_/', $remainder, $matches) ){
			$extent += strlen($matches[0]);
			$remainder = substr($remainder, $extent);
			$Element['text'] = $matches[1];
			$Element['attributes']['class'] = 'text-underline';

		}else{
			return;
		}


		return array(
			'extent' => $extent,
			'element' => $Element,
		);
	}
	/*
	 * END: Liturgical Element Processing
	 */



	/*
	 * Handlers
	 */
	protected function handle(array $Element){
		if (isset($Element['handler'])){
			if (!isset($Element['nonNestables'])){
				$Element['nonNestables'] = array();
			}

			if (is_string($Element['handler'])){
				$function = $Element['handler'];
				$argument = $Element['text'];
				unset($Element['text']);
				$destination = 'rawHtml';
			}else{
				$function = $Element['handler']['function'];
				$argument = $Element['handler']['argument'];
				$destination = $Element['handler']['destination'];
			}

			$Element[$destination] = $this->{$function}($argument, $Element['nonNestables']);

			if ($destination === 'handler'){
				$Element = $this->handle($Element);
			}

			unset($Element['handler']);
		}

		return $Element;
	}

	protected function handleElementRecursive(array $Element){
		return $this->elementApplyRecursive(array($this, 'handle'), $Element);
	}

	protected function handleElementsRecursive(array $Elements){
		return $this->elementsApplyRecursive(array($this, 'handle'), $Elements);
	}

	protected function elementApplyRecursive($closure, array $Element){
		$Element = call_user_func($closure, $Element);

		if (isset($Element['elements'])){
			$Element['elements'] = $this->elementsApplyRecursive($closure, $Element['elements']);
		}elseif (isset($Element['element'])){
			$Element['element'] = $this->elementApplyRecursive($closure, $Element['element']);
		}

		return $Element;
	}

	protected function elementApplyRecursiveDepthFirst($closure, array $Element){
		if (isset($Element['elements'])){
			$Element['elements'] = $this->elementsApplyRecursiveDepthFirst($closure, $Element['elements']);
		}
		elseif (isset($Element['element'])){
			$Element['element'] = $this->elementsApplyRecursiveDepthFirst($closure, $Element['element']);
		}

		$Element = call_user_func($closure, $Element);

		return $Element;
	}

	protected function elementsApplyRecursive($closure, array $Elements){
		foreach ($Elements as &$Element){
			$Element = $this->elementApplyRecursive($closure, $Element);
		}

		return $Elements;
	}

	protected function elementsApplyRecursiveDepthFirst($closure, array $Elements){
		foreach ($Elements as &$Element){
			$Element = $this->elementApplyRecursiveDepthFirst($closure, $Element);
		}

		return $Elements;
	}

	protected function element(array $Element){
		if ($this->safeMode){
			$Element = $this->sanitiseElement($Element);
		}

		# identity map if element has no handler
		$Element = $this->handle($Element);

		$hasName = isset($Element['name']);

		$markup = '';

		if ($hasName){
			$markup .= '<' . $Element['name'];

			if (isset($Element['attributes'])){
				foreach ($Element['attributes'] as $name => $value){
					if ($value === null){
						continue;
					}

					$markup .= " $name=\"".self::escape($value).'"';
				}
			}
		}

		$permitRawHtml = false;

		if (isset($Element['text'])){
			$text = $Element['text'];
		}
		// very strongly consider an alternative if you're writing an
		// extension
		else if (isset($Element['rawHtml'])){
			$text = $Element['rawHtml'];

			$allowRawHtmlInSafeMode = isset($Element['allowRawHtmlInSafeMode']) && $Element['allowRawHtmlInSafeMode'];
			$permitRawHtml = !$this->safeMode || $allowRawHtmlInSafeMode;
		}

		$hasContent = isset($text) || isset($Element['element']) || isset($Element['elements']);

		if ($hasContent){
			$markup .= $hasName ? '>' : '';

			if (isset($Element['elements'])){
				$markup .= $this->elements($Element['elements']);
			}else if (isset($Element['element'])){
				$markup .= $this->element($Element['element']);
			}else{
				if (!$permitRawHtml){
					$markup .= self::escape($text, true);
				}else{
					$markup .= $text;
				}
			}

			$markup .= $hasName ? '</' . $Element['name'] . '>' : '';
		}else if ($hasName){
			$markup .= ' />';
		}

		return $markup;
	}


	protected function elements(array $Elements){
		$markup = '';

		$autoBreak = true;

		foreach ($Elements as $Element){
			if (empty($Element)){
				continue;
			}

			$autoBreakNext = (isset($Element['autobreak']) ? $Element['autobreak'] : isset($Element['name']) );
			// (autobreak === false) covers both sides of an element
			$autoBreak = !$autoBreak ? $autoBreak : $autoBreakNext;

			$markup .= ($autoBreak ? "\n" : '') . $this->element($Element);
			$autoBreak = $autoBreakNext;
		}

		$markup .= $autoBreak ? "\n" : '';

		return $markup;
	}

    # ~

	protected function li($lines){
		$Elements = $this->linesElements($lines);

		if ( ! in_array('', $lines) && isset($Elements[0]) && isset($Elements[0]['name']) && $Elements[0]['name'] === 'p' ) {
			unset($Elements[0]['name']);
		}

		return $Elements;
	}


	/*
	 * AST Convenience
	 *
	 * Replace occurrences $regexp with $Elements in $text. Return an array of
	 * elements representing the replacement.
	 */
	protected static function pregReplaceElements($regexp, $Elements, $text){
		$newElements = array();

		while (preg_match($regexp, $text, $matches, PREG_OFFSET_CAPTURE)){
			$offset = $matches[0][1];
			$before = substr($text, 0, $offset);
			$after = substr($text, $offset + strlen($matches[0][0]));

			$newElements[] = array('text' => $before);

			foreach ($Elements as $Element){
				$newElements[] = $Element;
			}

			$text = $after;
		}

		$newElements[] = array('text' => $text);

		return $newElements;
	}



	/*
	 * Deprecated Methods
	 */
	protected function sanitiseElement(array $Element){
		static $goodAttribute = '/^[a-zA-Z0-9][a-zA-Z0-9-_]*+$/';
		static $safeUrlNameToAtt  = array(
			'a'   => 'href',
			'img' => 'src',
		);

		if ( ! isset($Element['name'])){
			unset($Element['attributes']);
			return $Element;
		}

		if (isset($safeUrlNameToAtt[$Element['name']])){
			$Element = $this->filterUnsafeUrlInAttribute($Element, $safeUrlNameToAtt[$Element['name']]);
		}

		if ( ! empty($Element['attributes'])){
			foreach ($Element['attributes'] as $att => $val){
				# filter out badly parsed attribute
				if ( ! preg_match($goodAttribute, $att)){
					unset($Element['attributes'][$att]);
				}
				# dump onevent attribute
				elseif (self::striAtStart($att, 'on')){
					unset($Element['attributes'][$att]);
				}
			}
		}

		return $Element;
	}

	protected function filterUnsafeUrlInAttribute(array $Element, $attribute){
		foreach ($this->safeLinksWhitelist as $scheme){
			if (self::striAtStart($Element['attributes'][$attribute], $scheme)){
				return $Element;
			}
		}

		$Element['attributes'][$attribute] = str_replace(':', '%3A', $Element['attributes'][$attribute]);

		return $Element;
	}
	/*
	 * END: Deprecated Methods
	 */



	/*
	 * Static methods
	 */
	protected static function escape($text, $allowQuotes = false){
		return htmlspecialchars($text, $allowQuotes ? ENT_NOQUOTES : ENT_QUOTES, 'UTF-8');
	}

	protected static function striAtStart($string, $needle){
		$len = strlen($needle);

		if ($len <= strlen($string)){
			return strtolower(substr($string, 0, $len)) === strtolower($needle);
		}

		return false;
	}

	static function instance($name = 'default'){
		if (isset(self::$instances[$name])){
			return self::$instances[$name];
		}

		$instance = new static();

		self::$instances[$name] = $instance;

		return $instance;
	}
	/*
	 * END: Static methods
	 */



	/*
	 * Read-Only Reference Data
	 */
	protected $specialCharacters = array(
		'\\', '`', '*', '_', '{', '}', '[', ']', '(', ')', '>', '#', '+', '-', '.', '!', '|', '~'
	);

	protected $StrongRegex = array(
		'*' => '/^[*]{2}((?:\\\\\*|[^*]|[*][^*]*+[*])+?)[*]{2}(?![*])/s',
		'_' => '/^__((?:\\\\_|[^_]|_[^_]*+_)+?)__(?!_)/us',
	);

	protected $EmRegex = array(
		'*' => '/^[*]((?:\\\\\*|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*])/s',
		'_' => '/^_((?:\\\\_|[^_]|__[^_]*__)+?)_(?!_)\b/us',
	);

	protected $regexHtmlAttribute = '[a-zA-Z_:][\w:.-]*+(?:\s*+=\s*+(?:[^"\'=<>`\s]+|"[^"]*+"|\'[^\']*+\'))?+';

	protected $voidElements = array(
		'area', 'base', 'br', 'col', 'command', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source',
	);

	protected $textLevelElements = array(
		'a', 'br', 'bdo', 'abbr', 'blink', 'nextid', 'acronym', 'basefont',
		'b', 'em', 'big', 'cite', 'small', 'spacer', 'listing',
		'i', 'rp', 'del', 'code',          'strike', 'marquee',
		'q', 'rt', 'ins', 'font',          'strong',
		's', 'tt', 'kbd', 'mark',
		'u', 'xm', 'sub', 'nobr',
					'sup', 'ruby',
					'var', 'span',
					'wbr', 'time',
	);
	/*
	 * END: Read-Only Reference Data
	 */
}
?>