<?php

/**
 * Create, Update and Get Entries from the Karte von Morgen
 *
 * @author   Sjoerd Takken
 * @copyright  No Copyright.
 * @license    GNU/GPLv2, see https://www.gnu.org/licenses/gpl-2.0.html
 */
class KVMInterfaceHandleEntries 
{
  public CONST KVM_ENTRY_ID = 'kvm_entry_id';

  private $entriesApi;
  private $parent;

  public function __construct($parent) 
  {
    $this->parent = $parent;
  }

  public function get_parent()
  {
    return $this->parent;
  }

  public function initialize()
  {
    // After all Plugins are loaded we start to deal with events.
    add_filter( 'wp_loaded', array( $this, 'start' ));
  }

  public function start() 
  {
    $this->entriesApi = new OpenFairDBEntriesApi(
      $this->get_parent()->get_client(),
      $this->get_parent()->get_config());

  }

  public function save_entry($wpInitiative)
  {
    $this->get_parent()->update_config();
    $api = $this->getEntriesApi();

    $id = $wpInitiative->get_kvm_id();

    $wpLocation = $wpInitiative->get_location();
    $wpLocationHelper = new WPLocationHelper();
    $wpLocationHelper->fill_by_osm_nominatim($wpLocation);

    if(empty($id))
    {
      $id = $api->entriesPost($wpInitiative);
      $id = str_replace('"', '', $id);
      $wpInitiative->set_kvm_id($id);
    }
    else
    {
      $wpInitiativen2 = $api->entriesGet(array($id));
      try
      {
        $api->entriesPut($wpInitiative, 
                         $id, 
                         reset($wpInitiativen2)->get_kvm_version() + 1);
      }
      catch(OpenFairDBApiException $e)
      {
        echo 'CODE ' . $e->getCode();
        if($e->getCode() == 404)
        {
          echo 'OpenFairDB entriesPut, id not found' . $id;
        }
        else
        {
          throw $e;
        }
      }
    }
    return $id;
  }


  /**
   * Method for Testing
   */
  public function get_entries()
  {
    $this->get_parent()->update_config();
    $api = $this->getEntriesApi();
    $wpInitiativen= $api->searchGet('0,0,50,50',null,null,
                          null,null,null, 10); 
    $ids = array();
    foreach($wpInitiativen as $wpInitiative)
    {
      array_push($ids, $wpInitiative->get_id());
    }
    return $this->get_entries_by_ids($ids);
  }

  public function get_entries_by_ids($ids)
  {
    try
    {
      $api = $this->getEntriesApi();
      return $api->entriesGet($ids);
    }
    catch(OpenFairDBApiException $e)
    {
      if($e->getCode() == 404)
      {
        echo 'OpenFairDB entriesGet, ids not found' . 
          implode(',', $ids);
        return array();
      }
      else
      {
        throw $e;
      }
    }
  }

  public function getEntriesApi()
  {
    return $this->entriesApi;
  }
}
