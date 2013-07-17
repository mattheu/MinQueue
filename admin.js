jQuery( document ).ready( function() {

	/**
	 * Hide or show an element depending on which input is checked.
	 *
	 * @param  fieldOne jQuery object for the first element
	 * @param  fieldOne jQuery object for the second element
	 * @param  fieldOne jQuery object for the input relating to the first element. Note the input for the second must be a sibling. Note that this function only supports 2 fields.
	 * @return null
	 */
	var minqueueToggle = function( fieldOne, fieldTwo, fieldOneInput ) {

		var toggler = function () {

			if ( fieldOneInput.is( ':checked' ) ) {
				fieldOne.slideDown( 100 );
				fieldTwo.slideUp( 100 );
			} else {
				fieldOne.slideUp( 100 );
				fieldTwo.slideDown( 100 );
			}

		};

		toggler();
		fieldOneInput.siblings( 'input[type=radio]' ).andSelf().change( toggler );

	};

	minqueueToggle( jQuery('#field_manual_scripts'), jQuery('#field_disabled_scripts'), jQuery( '#minqueue_options_scripts_method_manual' ) );
	minqueueToggle( jQuery('#field_manual_styles'),  jQuery('#field_disabled_styles'),  jQuery( '#minqueue_options_styles_method_manual' )  );

	/**
	 * Clone input.
	 *
	 * Clones a hidden input  (must have class input-template), and appends it.
	 * Updates numbers in the ID to reflect the current number of inputs.
	 *
	 * @param  container element
	 * @return null
	 */
	var minqueueFieldCloner = function( container ) {

		var template = container.find( '.input-template' );

		var buttonAddNew = jQuery( '<button class="button">Add New Queue</button>' );
		buttonAddNew.appendTo( container );

		buttonAddNew.click( function(e){

			e.preventDefault();

			// Clone the hidden input.
			var newInput = template.clone();

			// Update the ID
			newInput.attr('ID', newInput.attr( 'ID' ).replace('_hidden', '_' + ( container.find( 'textarea' ).length - 1 ) ) );

			// Insert & Show
			buttonAddNew.before( newInput.show() );

		} );

	};

	minqueueFieldCloner( jQuery( '#field_manual_scripts' ) );
	minqueueFieldCloner( jQuery( '#field_manual_styles' ) );

} );