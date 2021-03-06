<?php if ( ! DEFINED('BASEPATH') ) die ('No direct script access allowed.');


 
/**
 * Admin
 * 
 * @package 	PHP Project Tracker
 * @author 		Mark Skilbeck
 * @copyright 	2009, Mahcuz.com, Inc.
 * @version 	0.0.1
 * @access 		public
 */
class Admin extends Controller
{
	
	/**
	 * @access public
	 */
	function Admin()
	{
		parent::Controller();
		
		# Load libraries we need.
			$this->load->library('session');
			$this->load->library('form_validation');
			# Set our delimiters (form_val)
				$this->form_validation->set_error_delimiters('<li>', '</li>');
		
		# Load models (second param is alias).
			$this->load->model('Project_model', 'project');
			$this->load->model('User_model', 'user');
			
		# Load helpers.
			$this->load->helper('url');
			$this->load->helper('form');
			$this->load->helper('user_auth');
		
		# Profiling - DEBUGGING ONLY
			$this->output->enable_profiler( TRUE );
		
		# check with user_auth that user is an admin.
		# basically compares param 1 against param 2, in the 3rd param.
			if ( session_exists( 'access_level', 4, 'session' ) === FALSE 
					&& $this->uri->segment( 2 ) != 'login' )
			{
				# Add a session so we can redirect users to where they came from
				# before being required to log in.
				# This is the current URL.
				$this->session->set_userdata('return_url', current_url());
				redirect('admin/login/');
			}	
			else
			{
				$this->_username 		= $this->session->userdata( 'username' );
				$this->_access_level	= $this->session->userdata( 'access_lvl' );
			}	
	}
	
	/**
	 * @access public
	 */
	public function index()
	{
		$vars['area']   = 'back';
		$vars['module'] = 'main';
		
		$this->load->view('loader', $vars);
	}
	
	
	/**
	 * @access public
	 */
	public function create()
	{
		$vars['area']   = 'back';
		$vars['module'] = 'create';
		
		# Was the form submitted?
		if( $this->input->post( 'submit_project' ) !== FALSE )
		{
			# Let's create some validation rules, using the 
			# form validation class.
			$rules = 
				array (
						array (
							'field' => 'project_name', # POST key.
							'label' => 'Project Name', # Human form.
							'rules' => 'trim|required|min_length[1]|max_length[40]|callback_project_exists[true]'
							),
						array (
							'field' => 'project_author',
							'label' => 'Project Author',
							'rules' => 'trim|required|min_length[1]|max_length[40]|alpha|callback_username_exists[true]'
							),
						array (
							'field' => 'project_desc',
							'label' => 'Project Description',
							'rules' => 'trim|required|min_length[1]'
							)
						);
			
			$this->form_validation->set_rules( $rules );
			
			# Run the validation, if evaluates for FALSE, return errors.
			if ( $this->form_validation->run() === FALSE )
			{
				$this->load->view( 'loader', $vars );
			}
			else
			{
				# Alias: URL friendly.
				$alias = strtolower( str_replace( ' ', '-', $this->input->post('project_name') ) );
				$data = 
					array (
						'project_name' 		=> $this->input->post('project_name'),
						'project_author' 	=> $this->input->post('project_author'),
						'project_desc'		=> $this->input->post('project_desc'),
						'alias'				=> $alias
						);
				# Pass our $data array to the commit_new_project() method.
				# Using Active Record, CI will sort the data for us, cleaning it
				# etc.
				$this->project->commit_new_project( $data );
				
				$vars['page'] = "done.php";
				$vars['new_release_link'] = "admin/newrelease/{$alias}";
				
				$this->load->view( 'loader.php', $vars );
			}
		}
		else
		{
			# Let's show the upload form
			$this->load->view('loader.php', $vars);
		}
	}
	
	public function edit()
	{
		$vars['area']	= 'back';
		$vars['module'] = 'edit';
		$vars['page']	= 'view_all';
		
		# No project name is given.
		if ( $this->uri->segment( 3 ) === FALSE )
		{
			# Load all projects into variable for use in view.
			$vars['projects'] = $this->project->get_all_projects();
			# Load the view, passing the data in param 2.
			$this->load->view('loader', $vars);
		}
		
		# Project name is given.
		else
		{
			# Check the project exists.
			if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE )
			{
				# Throw error.
				show_error('That project does not exist.');
			}
			
			# Load the project info.
			$vars['project'] = $this->project->get_project( $this->uri->segment( 3 ) );
			
			# Has the form been submitted?
			if ( $this->input->post('update') !== FALSE )
			{
				# Set some validation rules.
				$rules = 
					array ( 
						array (
							'field' => 'project_name',
							'label' => 'Project Name',
							'rules' => 'trim|required|min_length[1]|max_length[40]|callback_project_exists[true]'
						),
						array (
							'field' => 'project_author',
							'label' => 'Project Author',
							'rules' => 'trim|required|min_length[1]|max_length[40]|alpha|callback_username_exists[true]'
						),
						array (
							'field' => 'project_desc',
							'label' => 'Project Description',
							'rules' => 'trim|required|min_length[1]'
						)
					);
				# Set the validation rules.
				$this->form_validation->set_rules( $rules );
				
				# Run the validation. If it returns false ...
				if ( $this->form_validation->run() === FALSE )
				{
					# Reload the edit page. 
					# The edit page will detect the errors and show them.
					$vars['page'] = 'edit_form';
					$this->load->view('loader', $vars);
					return;
				}
				
				# Validation went smoothly.		
				else
				{
					# Now, because we're allowing the changing of the project name.,
					# if it has been changed (via the form), we also need to update
					# the project names for the changelogs and the releases.
					# To avoid changing the names when they don't need to be, we'll
					# compare the names.
					if ( $this->input->post('project_name') !== $vars['project']->project_name )
					{
						# It needs to be updated.
						$this->project->update_all_project_names( $vars['project']->project_name, 
																  $this->input->post('project_name' ) );
					}
					
					# Build data for update.
					$alias = strtolower( str_replace( ' ', '-', $this->input->post('project_name') ) );
					$data = 
						array (	
							'project_name' 	 => $this->input->post('project_name'),
							'project_author' => $this->input->post('project_author'),
							'project_desc' 	 => $this->input->post('project_desc'),
							'alias' 		 => $alias
						);
					
					# Run the update.
					$this->project->update_project( $vars['project']->project_name, $data );
					
					# Project update is done. 
					# Set page var, etc.
					$vars['page'] 	  = 'success';
					$vars['red_to']   = site_url('admin');
					$vars['red_time'] = 3;
					$this->load->view('loader', $vars);	
					return;
				}			
				
			}
			# Set page to the edit form.
			$vars['page'] 	 = 'edit_form';
			$this->load->view('loader', $vars);
		}
	}
	
	public function delete ( )
	{
		$vars['area']	= 'back';
		$vars['module'] = 'delete';
		$vars['page']	= 'view_all';
		
		# No project given.
		if ( $this->uri->segment( 3 ) === FALSE )
		{
			# Load every project.
			$vars['projects'] = $this->project->get_all_projects();
			# Load view.
			$this->load->view('loader', $vars);
			# Break.
			return;
		}
		
		# Project given. 
		# Check that the given project exists.
		if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE )
		{
			# Throw error.
			show_error('That project does not exist.');
			# No need to return here.
		}
		
		$project = $this->project->get_project( $this->uri->segment( 3 ) );
		
		# We need to remove all releases and changelogs associated with this project.
		$this->project->delete_associated_changelogs( $project->project_name );
		# Do the same for releases.
		$this->project->delete_associated_releases( $project->project_name );
		# And then finally remove the project from the projects table.
		$this->project->delete_project( $project->project_name );
		
		# Load 'project deleted' view.
		$vars['page'] = 'success';
		
		$this->load->view('loader', $vars);
	}
	
	public function newrelease( )
	{	
		$vars['area'] 	= 'back';
		$vars['module'] = 'newrelease';
		$vars['page'] 	= 'add_form';
		
		if ( $this->uri->segment( 3 ) === FALSE )
		{
			$vars['page'] 				= 'view_all';
			# Grab all the projects via our Project model
			# If there are no projects, the value returned is NULL.
			$vars['project_results']	= $this->project->get_all_projects();
			$this->load->view('loader', $vars);
		}
		else
		{
		
			# Grab project info.	
			# If there are no projects that match, $project will be NULL.
			$vars['project'] 		= $this->project->get_project( $this->uri->segment( 3 ) );
			if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE )
			{
				die( "That project doesn't exist." );
			}	
			# $project_info will hold data such as latest release, etc, providing there
			# is a release.
			if ( (bool) $vars['project']->has_release && $vars['project'] !== NULL )
			{
				$vars['project_info']	= $this->project->get_latest_project_info( $vars['project']->project_name );
				# $project_log holds ALL change logs associated with the project,
				# providing there is a valid project (with releases).
				$vars['project_log']	= $this->project->get_latest_changelog( $vars['project']->project_name, $vars['project_info']->project_version );
			}
			else
			{
				$vars['project_info']	= NULL;
				$vars['project_log']	= NULL;
			}
			if( $this->input->post( 'submit_release' ) !== FALSE )
			{
				# Run a few validation tests.
				# With arrays, remember your [] brackets.
				$rules = 
					array (
						array (
							'field' => 'project_name',
							'label' => 'Project Name',
							'rules' => 'required|min_length[4]|callback_project_exists[false]'
							),
						array (
							'field' => 'project_author',
							'label' => 'Project Author',
							'rules' => 'required|min_length[4]|callback_username_exists'
							),
						array (
							'field' => 'project_version',
							'label' => 'Project Version',
							'rules' => 'required|min_length[1]|callback_newer_version'
							)
					);
				
				$this->form_validation->set_rules( $rules );
				
				if ( $this->form_validation->run() !== TRUE )
				{
					# Form validation came back false
					# Show the upload form with errors.
					$vars['page'] = 'add_form';
					$this->load->view( 'loader', $vars );
				}
				
				else
				{
					# Form validation was a-ok.
					# We use a couple of extra libraries here.
					# The zip library, to create a zip archive of the
					# project file; and the upload library, to handle 
					# the uploading of the file. 
					# Check files exist.
					if ( $_FILES['file']['error'] === 0 )
					{
						$rand = rand();
						$upload_path   = str_replace('\\', '/', BASEPATH . 'uploads/' . $rand . $_FILES['file']['name']);
						
						if ( copy( $_FILES['file']['tmp_name'], $upload_path ) === FALSE )
						{
							$vars['file_error'] = "Error.";
							$vars['page'] 		= 'add_form';
							$this->load->view( 'loader', $vars );
						}
						
						else
						{
							# TODO: Tidy the file upload, and zip stuff up.
							# File uploaded. Let's archive.
							$this->load->library('zip');
							$file_path = $upload_path . "/" . $_FILES['file']['name'];
							$arc_path  = BASEPATH . "uploads/zip/" . $_FILES['file']['name'] . ".zip";
							$this->load->library( 'zip' );
							
							# Read the uploaded file into a string for ZIP lib.
							$this->zip->read_file( $file_path );
							
							# And archive it.
							$this->zip->archive( $arc_path );
							
							# Now we have the archive done, let's do the database work.
							# Do we have to update the has_release flag?
							if ( (bool) $vars['project']->has_release === FALSE )
							{
								$this->project->set_has_release( TRUE, $this->uri->segment( 3 ) );
							}
							
							# Set the data to be passed to the commit_new_release() method.
							$data = 
								array (
									'project_name' 		=> $this->input->post( 'project_name' ),
									'project_author' 	=> $this->input->post( 'project_author' ),
									'project_stage' 	=> $this->input->post( 'project_stage' ),
									'project_version' 	=> $this->input->post( 'project_version' ),
									'project_download' 	=> $arc_path
								);
							
							# Run the commit.
							$this->project->commit_new_release( $data );
							
							# Set misc vars.
							$vars['page'] 				= "release_added";
							$vars['release_version'] 	= $this->input->post( 'project_version' );
							$vars['project_name']		= $this->input->post( 'project_name' );
							$vars['changelog_link']		= "admin/addchangelog/{$this->uri->segment( 3 )}/{$vars['release_version']}/";
							$vars['version_link']		= "main/viewrelease/{$this->uri->segment( 3 )}/{$vars['release_version']}/";
							
							$this->load->view('loader', $vars);
						}
					}	
					
					else
					{
						die( "No File Uploaded" );
					}			
				}
			}
			
			else
			{
				$this->load->view('loader', $vars);
			}
		}
	}
	
	public function editrelease ( )
	{
		$vars['area']   = 'back';
		$vars['module'] = 'editrelease';
		
		# If we have no project given.
		if ( $this->uri->segment( 3 ) === FALSE )
		{
			# Grab all projects.
			$vars['projects'] = $this->project->get_all_projects();
			$vars['page']	  = 'view_all';
			
			# Load the view.
			$this->load->view('loader', $vars);
			
			# Break out
			return;
		}
		
		# We have a project name, but no release ver.
		if ( $this->uri->segment( 4 ) === FALSE )
		{
			# Check that the given project exists.
			if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE )
			{
				show_error('That project does not exist.');
			}
			
			# Create variables containing project information.
			$vars['project'] = $this->project->get_project( $this->uri->segment( 3 ) );
			$vars['page']	 = 'view_project';
			
			# Load view.
			$this->load->view('loader', $vars);
		}
	}
	
	/* Changelog stuff. */
	
	public function addchangelog ( ) 
	{
		$vars['area']   = 'back';
		$vars['module'] = 'addchangelog';
		
		# Is there a project given?
		if ( $this->uri->segment( 3 ) === FALSE )
		{
			# Get all projects.
			$vars['projects'] = $this->project->get_all_projects();
			$vars['page']	  = 'view_all';
			$this->load->view('loader', $vars);
		}
		
		# No version given.
		elseif ( $this->uri->segment( 4 ) === FALSE )
		{
			# Check the project exists. segment 3 of uri.
			if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE ) 
			{
				show_error('That project does not exist.');
			}
			
			# Load up the relevant data (project info and it's releases).
			$vars['project'] 			= $this->project->get_project( $this->uri->segment( 3 ) );
			$vars['project_releases'] 	= $this->project->get_releases_by_project( $vars['project']->project_name );
			$vars['page']				= 'view_single';

			$this->load->view('loader', $vars);
		}
		
		# Version and Proj given.
		else
		{
			# Check that the project exists by (alias).
			if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE )
			{
				show_error('That project does not exist.');
			}
			
			# Check that the given release exists for the given project.
			if ( $this->project->release_exists_by_alias( $this->uri->segment( 3 ),
			  		 $this->uri->segment( 4 ) ) === FALSE )
			{
				show_error('That release does not exist for the given project.');
			}
			
			# Both release and project are valid.
			# Load relevant project data to be passed to the view.
			$vars['project'] = $this->project->get_project( $this->uri->segment( 3 ) );
			$vars['release'] = $this->uri->segment( 4 );

			# Form was submitted.
			if ( $this->input->post('add_changelogs') !== FALSE )
			{
				# TODO: Fix this.
				# No logs were passed. 
				if ( count ( $this->input->post('title') ) < 1 )
				{
					show_error('No logs passed.');
				}

				# TODO: Fix this.
				# Should this happen in the controller or model?
				# We strip out any changelogs that weren't filled out.
				# By this, I mean not any logs that had a single section missing, but logs
				# that are completely empty - title, desc, type.
				$this->remove_empty_logs( );

				# We require at least the title of the changelog.
				# Note: we use [] to signify an array.
				$this->form_validation->set_rules('title[]', 'Changelog Title', 'trim|required|min_length[1]');

				if ( $this->form_validation->run() !== FALSE )
				{
					# Passed validation. Add to database.
					$data = 
						array (
							'project_name' 		=> $vars['project']->project_name,
							'project_version' 	=> $vars['release']
						);

					# Loop through the POSTed data, creating a new array of data
					# to pass the the commit_changelog() method.
					foreach ( $_POST['title'] as $key => $title )
					{
						$data['log_type'] 	= $_POST['type'][$key];
						$data['log_title']	= $_POST['title'][$key];
						$data['log_desc']	= $_POST['desc'][$key];

						# Commit it.
						$this->project->commit_changelog( $data );
					}

					# TODO: Change this.
					# Do we want to redirect to the 'view' page for the project in question
					# or do we want to do something else? Redirect to view the changelog,
					# perhaps?
					redirect('main/view/' . $vars['project']->project_name);
				}

				# The form validation failed.
				else
				{
					$vars['page'] = 'add_page';
					$this->load->view('loader', $vars);
				}
			}

			# No form was submitted. Show the 'add_page' form.
			else
			{
				$vars['page'] 	 = 'add_page';
				$this->load->view('loader', $vars);
			}
			
		}
	} 
	
	public function editchangelog ( )
	{
		$vars['area']	= 'back';
		$vars['module'] = 'editchangelog';
		
		# If we don't have a project name.
		if ( $this->uri->segment( 3 ) === FALSE )
		{
			# show all projects.
			$vars['projects'] = $this->project->get_all_projects();
			$vars['page']	  = 'view_all';
			
			$this->load->view('loader', $vars);
		}
		
		# We have a project name, but nothing else.
		elseif ( $this->uri->segment( 4 ) === FALSE )
		{
			# Project exists?
			if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE )
			{
				show_error("The given project <code>{$this->uri->segment( 3 )}</code> does not exist.");
			}
			
			# Load view with project info.
			$vars['project'] 			= $this->project->get_project( $this->uri->segment( 3 ) );
			$vars['project_releases'] 	= $this->project->get_releases_by_project( $vars['project']->project_name );
			$vars['page']				= 'view_project';
			
			$this->load->view('loader', $vars);
		}
		
		# We have project name, project release, but no changelog ID.
		elseif ( $this->uri->segment( 5 ) === FALSE )
		{
			# Project exists?
			if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE  )
			{
				show_error("The given project <code>{$this->uri->segment( 3 )}</code> does not exist.");
			}
			
			# Release exists?
			if ( $this->project->release_exists_by_alias( $this->uri->segment( 3 ),
														  $this->uri->segment( 4 ) ) === FALSE  )
			{
				show_error("The given release <code>{$this->uri->segment( 4 )} does not exist.");
			}
			
			# Project name and release number are valid.
			# Set the project and changelog vars.
			$vars['project'] 	= $this->project->get_project( $this->uri->segment( 3 ) );
			$vars['changelogs'] = $this->project->get_changelogs_by_release( $vars['project']->project_name,
																			 $this->uri->segment( 4 ) );
			$vars['page']		= 'view_single';
			
			# Load the page with the variables available to it.
			$this->load->view('loader', $vars);
		}
		
		# All information has been given.
		else
		{
			# Project exists?
			if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE  )
			{
				show_error("The given project <code>{$this->uri->segment( 3 )}</code> does not exist.");
			}
			
			# Release exists?
			if ( $this->project->release_exists_by_alias( $this->uri->segment( 3 ),
														  $this->uri->segment( 4 ) ) === FALSE  )
			{
				show_error("The given release <code>{$this->uri->segment( 4 )} does not exist.");
			}
			
			# Changelog id exists.
			if ( $this->project->changelog_exists_by_id( $this->uri->segment( 3 ),
														 $this->uri->segment( 4 ),
														 $this->uri->segment( 5 ) ) === FALSE )
			{
				show_error("The given ID is not valid or does not exist.");
			}
			
			# Load up the front end with form for editing.
			if ( $this->input->post('update') === FALSE )
			{
				# Set the needed information.
				$vars['project']   = $this->project->get_project( $this->uri->segment( 3 ) );
				$vars['changelog'] = $this->project->get_changelog_by_id( $this->uri->segment( 5 ) );
				$vars['page']	   = 'edit_form';
				
				# Load page with variables injected.
				$this->load->view('loader', $vars);
			}
			
			# We've got some POST data to work with and update the changelog with.
			else
			{
				# Set up some validation rules. All fields are required,
				# and cannot be less than 1 char.
				$rules =
					array (	
						array (
							'field' => 'title',
							'label' => 'Changelog Title',
							'rules' => 'required|min_length[1]'
						),
						array (	
							'field' => 'type',
							'label' => 'Changelog Type',
							'rules' => 'required|min_length[1]'
						),
						array (
							'field' => 'desc',
							'label' => 'Changelog Description',
							'rules' => 'required|min_length[1]'
						)
					);
				
				# Let the validation class know the rules.
				$this->form_validation->set_rules($rules);
				
				# Run the validator. 
				# Form doesn't validate.
				if ( $this->form_validation->run() === FALSE )
				{
					$vars['page'] = 'edit_form';
					$this->load->view('loader', $vars);
				}
				
				# Form validated successfully.
				else
				{
					# Build array of data we intend to pass to our Project model
					# so it can UPDATE the relevant changelog.
					$data =
						array (
							'log_type'  => $this->input->post('type'),
							'log_title' => $this->input->post('title'),
							'log_desc'  => $this->input->post('desc')
						);
						
					# Call the update_changelog(id, data) method.
					$this->project->update_changelog( $this->uri->segment( 5 ), $data );
					
					# Load success page.
					$vars['page'] 		= 'success';
					$vars['red_to'] 	= site_url('admin');
					$vars['red_time'] 	= 5;
					$this->load->view('loader', $vars);
				}
			}
			
		}
		
	}
	
	public function deletechangelog ( )
	{
		$vars['area']   = 'back';
		$vars['module'] = 'deletechangelog';

		# Do we have a project given?
		if ( $this->uri->segment( 3 ) === FALSE )
		{
			# Load changelogs.
			$vars['projects'] = $this->project->get_all_projects();
			
			# Set page.
			$vars['page'] 		= 'view_all';
			
			$this->load->view('loader', $vars);
		}
		
		# Project name has been provided (alias, in fact), project version has NOT 
		# been given.
		elseif ( $this->uri->segment( 4 ) === FALSE )
		{
			# Check the project exists, via the Project model methods.
			if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE )
			{
				show_error('That project does not exist.');
			}
			
			# Load up the project info and releases for that project.
			$vars['project']  = $this->project->get_project( $this->uri->segment( 3 ) );
			$vars['releases'] = $this->project->get_releases_by_project( $vars['project']->project_name );
			$vars['page']	  = 'view_project';
			
			$this->load->view('loader', $vars);
		}
		
		# Project name and release version have been provided.
		elseif ( $this->uri->segment( 5 ) === FALSE )
		{
			# Make sure project exists.
			if ( $this->project->project_Exists_by_alias( $this->uri->segment( 3 ) ) === FALSE )
			{
				show_error('That project does not exist.');
			}
			
			# And the release is valid for the given project.
			if ( $this->project->release_exists_by_alias( $this->uri->segment( 3 ), 
														  $this->uri->segment( 4 ) ) === FALSE )
			{
				show_error('The given project and release version do not match.');
			}
			
			# Project and release version are OK!
			# Load proj info and changelogs.
			$vars['project'] 	= $this->project->get_project( $this->uri->segment( 3 ) );
			$vars['changelogs'] = $this->project->get_changelogs_by_release( $vars['project']->project_name,
																			 $this->uri->segment( 4 ) );
			$vars['page']		= 'view_single';
			
			$this->load->view('loader', $vars);
		}
		
		# Everything has been given. Project, release, and log ID.
		else
		{
			# Project exists?
			if ( $this->project->project_exists_by_alias( $this->uri->segment( 3 ) ) === FALSE  )
			{
				show_error("The given project <code>{$this->uri->segment( 3 )}</code> does not exist.");
			}

			# Release exists?
			if ( $this->project->release_exists_by_alias( $this->uri->segment( 3 ),
														  $this->uri->segment( 4 ) ) === FALSE  )
			{
				show_error("The given release <code>{$this->uri->segment( 4 )} does not exist.");
			}

			# Changelog id exists.
			if ( $this->project->changelog_exists_by_id( $this->uri->segment( 3 ),
														 $this->uri->segment( 4 ),
														 $this->uri->segment( 5 ) ) === FALSE )
			{
				show_error("The given ID is not valid or does not exist.");
			}
			
			# Build criteria for deletion.
			$data = 
				array (	
					'id' => $this->uri->segment( 5 )
				);
				
			# Delete the changelog :( byebye!
			$this->project->delete_changelog( $data );
			
			# Redirect to the given project and release's deletechangelog page.
			redirect('admin/deletechangelog/' . $this->uri->segment( 3 ) . '/' . $this->uri->segment( 4 ) );
		}
	} 
	
	public function logout()
	{
		# Destroy CI's session.
		$this->session->sess_destroy();
		# and redirect to the root of site.
		redirect( '' );
	} 
	
	public function login()
	{
		$vars['area']   = 'back';
		$vars['module'] = 'login';
				
		# Was the forum submitted?
		if( $this->input->post('log_in') !== FALSE )
		{
			# Form validation stuff.
			$rules = 
				array (
					array (
						'field' => 'username',
						'label' => 'Username',
						'rules' => 'required|min_length[4]|max_length[16]|callback_username_exists[true]'
						),
					array (
						'field' => 'password',
						'label' => 'Password',
						'rules' => 'required|min_length[4]|max_length[16]|callback_password_username_match'
						)
					);
			$this->form_validation->set_rules( $rules );
			if ( $this->form_validation->run() === TRUE )
			{
				# Username and password matched.
				# Let's set the session data; access level, username.
				$userdata =
					array (
						'access_level' => $this->user->get_access_level( $this->input->post('username') ),
						'username'	   => $this->input->post('username')
						);
				$this->session->set_userdata( $userdata );
				# Set misc variables.
				$vars['page'] = "loggedin.php";
				$vars['red_to'] = $this->session->userdata('return_url');
				$vars['red_time'] = 3;
				$this->load->view( 'loader', $vars );
			}
			else
			{
				$this->load->view( 'loader', $vars );
			}
		}
		else
		{
			$this->load->view( 'loader', $vars );
		}
	}
	
	public function remove_empty_logs ( )
	{
		foreach ( $_POST['title'] as $key => $title )
		{
			if ( $_POST['title'][$key] == '' && $_POST['type'][$key] == '' && $_POST['desc'][$key] == '' )
			{
				unset ( $_POST['title'][$key] );
				unset ( $_POST['type'][$key] );
				unset ( $_POST['desc'][$key] ); 
			}
		}
	}
	
	public function username_exists ( $username, $switch )
	{
		# Callback to check username exists in database.
		if( $switch == 'true' )
		{
			if ( ! $this->user->username_exists( $username ) )
			{
				$this->form_validation->set_message( 'username_exists', 'That username doesn\'t exist' );
				return FALSE;
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			# code...
		}
	}

	public function project_exists ( $project, $switch )
	{
		# Used as a callback for form validation.
		# Does the project exist? The model will let us know!
		if( $switch == 'true' )
		{
			if ( $this->project->project_exists( $project ) === TRUE )
			{
				$this->form_validation->set_message( 'project_exists', 'That project already exists.' );
				return FALSE;
			}
			else
			{
				return TRUE;
			}
		}
		else
		{
			if ( $this->project->project_exists( $project ) === FALSE )
			{
				$this->form_validation->set_message( 'project_exists', 'That project doesn\'t exist.' );
				return FALSE;
			}
			else
			{
				return TRUE;
			}
		}
	}
	
	public function password_username_match ( $password )
	{
		# Callback for comparing the given password for 
		# username.
		$username = $this->input->post( 'username' );
		if ( $this->user->password_username_match( $username, $password ) )
		{
			return TRUE;
		}
		else
		{
			$this->form_validation->set_message( 'password_username_match', 'Password and Username don\'t match.' );
			return FALSE;
		}
	}
	
	/**
	 * Callback function.
	 * Used for new releases, to check that the given new releases
	 * project version, is greater than the last version given.
	 * To get this far, it has to be a valid project, so we can
	 * skip checking the projects validity.
	 */
	public function newer_version ( $version )
	{
		$project		= $this->uri->segment( 3 );
		$project_info   = $this->project->get_project( $project );
		if ( (bool) $project_info->has_release )
		{
			$latest_project = $this->project->get_latest_project_info( $project_info->project_name );
			if ( $version > $latest_project->project_version )
			{
				return TRUE;
			}
			else
			{
				$this->form_validation->set_message( 'newer_version', "The project version you supplied is older than the current version ({$latest_project->project_version})." );
				return FALSE;
			}
		}
		else
		{
			return TRUE;
		}
	}
	
	public function createrelease()
	{
		$name 			= $_POST['project_name'];
		$description 	= $_POST['project_desc'];
		$author 		= $_POST['project_author'];
		$stage 			= $_POST['project_stage'];
		$version 		= $_POST['project_version'];
		$dl_avail 		= ($this->no_file ? "no" : "yes");
		$dl_link		= ROOTPATH . "dev_projects/zip/{$_POST['project_name']}-{$_POST['project_version']}.zip";
		$rel_ag 		= "none";
		
		$this->db->query("INSERT INTO `project_releases` VALUES
		(null, '{$name}', '{$description}', '{$version}', '{$rel_ag}', '{$dl_avail}', '{$dl_link}')");
	}
	 
}


?>