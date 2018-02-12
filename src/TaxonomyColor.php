<?php
namespace Drupal\fullcalendar_view;

class TaxonomyColor {
  public function __construct() {
  }
  
  /**
   * Color input box for taxonomy terms of a vocabulary 
   *
   */
  public function colorInputBoxs($vid, array $defaultValues, $open = FALSE) {
    // Taxonomy color details.
    $elements = array(
      '#type' => 'details',
      '#title' => t('Colors for Taxonomies'),
      '#fieldset' => 'colors',
      '#open' => $open,
      '#prefix' => '<div id="color-taxonomies-div">',
      '#suffix' => '</div>',
      '#states' => array(
        // Only show this field when the 'vocabularies' is selected.
        'invisible' => [
          [':input[name="style_options[vocabularies]"]' => ['value' => '']],
        ],
      ),
    );
   // Term IDs of the vocabulary.
   $terms = $this->getTermIDs($vid);
   if (isset($terms[$vid])) {
     // Create a color box for each terms.
     foreach ($terms[$vid] as $taxonomy) {
       $color = isset($defaultValues[$taxonomy->id()]) ? $defaultValues[$taxonomy->id()] : '#3a87ad';
       $elements[$taxonomy->id()] = array(
         '#title' => $taxonomy->name->value,
         '#default_value' => $color,
         '#type' => 'color',
         '#states' => array(
           // Only show this field when the 'tax_field' is selected.
           'invisible' => [
             [':input[name="style_options[tax_field]"]' => ['value' => '']],
           ],
         ),
         '#attributes' => [
           'value' => $color,
           'name' => 'style_options[color_taxonomies][' . $taxonomy->id() . ']',
         ],
       );
     }
   }

    return $elements;
 }
  
 /*
  * Get all terms of a vocabulary.
  */
  private function getTermIDs($vid) {
    if (empty($vid)) {
      return [];
    }
    $terms = &drupal_static(__FUNCTION__);
    // Get taxonomy terms from database if they haven't been loaded.
    if (!isset($terms[$vid])) {      
      // Get terms Ids.
      $query = \Drupal::entityQuery('taxonomy_term');
      $query->condition('vid', $vid);
      $tids = $query->execute();     
      $terms[$vid] = \Drupal\taxonomy\Entity\Term::loadMultiple($tids);      
    }
    
    return $terms;
  }
}