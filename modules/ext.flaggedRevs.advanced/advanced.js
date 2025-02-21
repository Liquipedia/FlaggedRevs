/**
 * FlaggedRevs Advanced JavaScript
 *
 * @author Aaron Schulz
 * @author Krinkle <krinklemail@gmail.com> 2011
 */
( function () {
	'use strict';

	/* Dropdown collapse timer */
	var boxCollapseTimer = null;

	/* Expands flag info box details */
	function showBoxDetails() {
		$( '#mw-fr-revisiondetails' ).css( 'display', 'block' );
	}

	/* Collapses flag info box details */
	function hideBoxDetails() {
		$( '#mw-fr-revisiondetails' ).css( 'display', 'none' );
	}

	/**
	 * Checks if mouseOut event is for a child of parentId
	 *
	 * @param {jQuery.Event} e
	 * @param {string} parentId
	 * @return {boolean} True if given event object originated from a (direct or indirect)
	 * child element of an element with an id of parentId.
	 */
	function isMouseOutBubble( e, parentId ) {
		var nextParent,
			toNode = e.relatedTarget;

		if ( toNode ) {
			nextParent = toNode.parentNode;
			while ( nextParent ) {
				if ( nextParent.id === parentId ) {
					return true;
				}
				// next up
				nextParent = nextParent.parentNode;
			}
		}
		return false;
	}

	/**
	 * Expands flag info box details on mouseOver
	 *
	 * @this {jQuery}
	 */
	function onBoxMouseOver() {
		window.clearTimeout( boxCollapseTimer );
		boxCollapseTimer = null;
		showBoxDetails();
	}

	/**
	 * Hides flag info box details on mouseOut *except* for event bubbling
	 *
	 * @this {jQuery}
	 * @param {jQuery.Event} e
	 */
	function onBoxMouseOut( e ) {
		if ( !isMouseOutBubble( e, 'mw-fr-revisiontag' ) ) {
			boxCollapseTimer = window.setTimeout( hideBoxDetails, 150 );
		}
	}

	/**
	 * Toggles diffs
	 *
	 * @this {jQuery}
	 */
	function toggleDiff() {
		var $diff = $( '#mw-fr-stablediff' ),
			$toggle = $( '#mw-fr-difftoggle' );

		if ( $diff.length && $toggle.length ) {
			if ( $diff.css( 'display' ) === 'none' ) {
				// FIXME: Use CSS transition
				// eslint-disable-next-line no-jquery/no-animate-toggle
				$diff.show( 'slow' );
				$toggle.children( 'a' ).text( mw.msg( 'revreview-diff-toggle-hide' ) );
			} else {
				// FIXME: Use CSS transition
				// eslint-disable-next-line no-jquery/no-animate-toggle
				$diff.hide( 'slow' );
				$toggle.children( 'a' ).text( mw.msg( 'revreview-diff-toggle-show' ) );
			}
		}
	}

	/**
	 * Startup function
	 */
	function init() {
		// Enables rating detail box
		var $toggle = $( '#mw-fr-revisiontoggle' );

		if ( $toggle.length ) {
			hideBoxDetails(); // hide the initially displayed ratings
		}

		// Simple UI: Show the box on mouseOver
		$( '.fr-toggle-arrow#mw-fr-revisiontoggle' ).on( 'mouseover', onBoxMouseOver );
		$( '.flaggedrevs_short#mw-fr-revisiontag' ).on( 'mouseout', onBoxMouseOut );

		// Enables diff detail box and toggle
		$toggle = $( '#mw-fr-difftoggle' );
		if ( $toggle.length ) {
			$toggle.css( 'display', 'inline' ); // show toggle control
			$( '#mw-fr-stablediff' ).hide();
		}
		$toggle.children( 'a' ).on( 'click', toggleDiff );
	}

	// Perform some onload events:
	$( init );

}() );
