<?php
include '../../connectMySql.php';
include '../../loginverification.php';
include '../../includes/fast_api_helper.php';
if(logged_in()){

if (isset($_POST['keyword_id'])) {
    $keyword_id = $_POST['keyword_id'];
    $from_age = $_POST['from_age'];
    $to_age = $_POST['to_age'];

    $value ="";

    if ($_POST['keyword_id'] == 'Educational') {
    $value = "(category, website,from_age,to_age) VALUES
    ('Educational', 'khanacademy.org', $from_age,$to_age),
    ('Educational', 'coursera.org', $from_age,$to_age),
    ('Educational', 'edx.org', $from_age,$to_age),
    ('Educational', 'udemy.com', $from_age,$to_age),
    ('Educational', 'w3schools.com', $from_age,$to_age),
    ('Educational', 'tutorialspoint.com', $from_age,$to_age),
    ('Educational', 'academia.edu', $from_age,$to_age),
    ('Educational', 'quizlet.com', $from_age,$to_age),
    ('Educational', 'brilliant.org', $from_age,$to_age),
    ('Educational', 'futurelearn.com', $from_age,$to_age),
    ('Educational', 'skillshare.com', $from_age,$to_age),
    ('Educational', 'alison.com', $from_age,$to_age),
    ('Educational', 'codecademy.com', $from_age,$to_age),
    ('Educational', 'open.edu', $from_age,$to_age),
    ('Educational', 'wikiversity.org', $from_age,$to_age)";
} elseif ($_POST['keyword_id'] == 'News & Media') {
    $value = "(category, website,from_age,to_age) VALUES
    ('News & Media', 'cnn.com', $from_age,$to_age),
    ('News & Media', 'bbc.com', $from_age,$to_age),
    ('News & Media', 'nytimes.com', $from_age,$to_age),
    ('News & Media', 'inquirer.net', $from_age,$to_age),
    ('News & Media', 'philstar.com', $from_age,$to_age),
    ('News & Media', 'abs-cbn.com', $from_age,$to_age),
    ('News & Media', 'gmanetwork.com', $from_age,$to_age),
    ('News & Media', 'reuters.com', $from_age,$to_age),
    ('News & Media', 'rappler.com', $from_age,$to_age),
    ('News & Media', 'manilatimes.net', $from_age,$to_age),
    ('News & Media', 'foxnews.com', $from_age,$to_age),
    ('News & Media', 'aljazeera.com', $from_age,$to_age),
    ('News & Media', 'news.yahoo.com', $from_age,$to_age),
    ('News & Media', 'time.com', $from_age,$to_age),
    ('News & Media', 'nbcnews.com', $from_age,$to_age)";
} elseif ($_POST['keyword_id'] == 'Health & Wellness') {
    $value = "(category, website,from_age,to_age) VALUES
    ('Health & Wellness', 'webmd.com', $from_age,$to_age),
    ('Health & Wellness', 'mayoclinic.org', $from_age,$to_age),
    ('Health & Wellness', 'healthline.com', $from_age,$to_age),
    ('Health & Wellness', 'medlineplus.gov', $from_age,$to_age),
    ('Health & Wellness', 'who.int', $from_age,$to_age),
    ('Health & Wellness', 'cdc.gov', $from_age,$to_age),
    ('Health & Wellness', 'medicalnewstoday.com', $from_age,$to_age),
    ('Health & Wellness', 'nhs.uk', $from_age,$to_age),
    ('Health & Wellness', 'clevelandclinic.org', $from_age,$to_age),
    ('Health & Wellness', 'verywellhealth.com', $from_age,$to_age),
    ('Health & Wellness', 'drugs.com', $from_age,$to_age),
    ('Health & Wellness', 'everydayhealth.com', $from_age,$to_age),
    ('Health & Wellness', 'psychologytoday.com', $from_age,$to_age),
    ('Health & Wellness', 'ahealthyme.com', $from_age,$to_age),
    ('Health & Wellness', 'emedicinehealth.com', $from_age,$to_age)";
} elseif ($_POST['keyword_id'] == 'Government & Public Service') {
    $value = "(category, website,from_age,to_age) VALUES
    ('Government & Public Service', 'gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'bir.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'dswd.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'pagibigfund.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'sss.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'nbi.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'pnp.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'philgeps.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'dfa.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'comelec.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'customs.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'deped.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'doh.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'pco.gov.ph', $from_age,$to_age),
    ('Government & Public Service', 'senate.gov.ph', $from_age,$to_age)";
} elseif ($_POST['keyword_id'] == 'E-commerce') {
    $value = "(category, website,from_age,to_age) VALUES
    ('E-commerce', 'shopee.ph', $from_age,$to_age),
    ('E-commerce', 'lazada.com.ph', $from_age,$to_age),
    ('E-commerce', 'amazon.com', $from_age,$to_age),
    ('E-commerce', 'ebay.com', $from_age,$to_age),
    ('E-commerce', 'zalora.com.ph', $from_age,$to_age),
    ('E-commerce', 'aliexpress.com', $from_age,$to_age),
    ('E-commerce', 'carousell.ph', $from_age,$to_age),
    ('E-commerce', 'etsy.com', $from_age,$to_age),
    ('E-commerce', 'banggood.com', $from_age,$to_age),
    ('E-commerce', 'ubuy.com.ph', $from_age,$to_age),
    ('E-commerce', 'walmart.com', $from_age,$to_age),
    ('E-commerce', 'newegg.com', $from_age,$to_age),
    ('E-commerce', 'argomall.ph', $from_age,$to_age),
    ('E-commerce', 'samsung.com/ph', $from_age,$to_age),
    ('E-commerce', 'apple.com/ph', $from_age,$to_age)";
} elseif ($_POST['keyword_id'] == 'Banking & Finance') {
    $value = "(category, website,from_age,to_age) VALUES
    ('Banking & Finance', 'bdo.com.ph', $from_age,$to_age),
    ('Banking & Finance', 'bpi.com.ph', $from_age,$to_age),
    ('Banking & Finance', 'metrobank.com.ph', $from_age,$to_age),
    ('Banking & Finance', 'securitybank.com', $from_age,$to_age),
    ('Banking & Finance', 'unionbankph.com', $from_age,$to_age),
    ('Banking & Finance', 'rcbc.com', $from_age,$to_age),
    ('Banking & Finance', 'eastwestbanker.com', $from_age,$to_age),
    ('Banking & Finance', 'landbank.com', $from_age,$to_age),
    ('Banking & Finance', 'psbank.com.ph', $from_age,$to_age),
    ('Banking & Finance', 'chinabank.ph', $from_age,$to_age),
    ('Banking & Finance', 'maya.ph', $from_age,$to_age),
    ('Banking & Finance', 'gcash.com', $from_age,$to_age),
    ('Banking & Finance', 'coins.ph', $from_age,$to_age),
    ('Banking & Finance', 'truemoney.com.ph', $from_age,$to_age),
    ('Banking & Finance', 'pnb.com.ph', $from_age,$to_age)";
} elseif ($_POST['keyword_id'] == 'Productivity') {
    $value = "(category, website,from_age,to_age) VALUES
    ('Productivity', 'docs.google.com', $from_age,$to_age),
    ('Productivity', 'sheets.google.com', $from_age,$to_age),
    ('Productivity', 'calendar.google.com', $from_age,$to_age),
    ('Productivity', 'notion.so', $from_age,$to_age),
    ('Productivity', 'trello.com', $from_age,$to_age),
    ('Productivity', 'asana.com', $from_age,$to_age),
    ('Productivity', 'slack.com', $from_age,$to_age),
    ('Productivity', 'microsoft365.com', $from_age,$to_age),
    ('Productivity', 'dropbox.com', $from_age,$to_age),
    ('Productivity', 'evernote.com', $from_age,$to_age),
    ('Productivity', 'todoist.com', $from_age,$to_age),
    ('Productivity', 'clickup.com', $from_age,$to_age),
    ('Productivity', 'zoho.com', $from_age,$to_age),
    ('Productivity', 'airtable.com', $from_age,$to_age),
    ('Productivity', 'monday.com, $from_age,$to_age')";
} elseif ($_POST['keyword_id'] == 'Job & Career') {
    $value = "(category, website,from_age,to_age) VALUES
    ('Job & Career', 'jobstreet.com.ph', $from_age,$to_age),
    ('Job & Career', 'indeed.com', $from_age,$to_age),
    ('Job & Career', 'linkedin.com', $from_age,$to_age),
    ('Job & Career', 'kalibrr.com', $from_age,$to_age),
    ('Job & Career', 'workabroad.ph', $from_age,$to_age),
    ('Job & Career', 'bossjob.ph', $from_age,$to_age),
    ('Job & Career', 'onlinejobs.ph', $from_age,$to_age),
    ('Job & Career', 'freelancer.com', $from_age,$to_age),
    ('Job & Career', 'upwork.com', $from_age,$to_age),
    ('Job & Career', 'glassdoor.com', $from_age,$to_age),
    ('Job & Career', 'careerjet.ph', $from_age,$to_age),
    ('Job & Career', 'jobbank.gc.ca', $from_age,$to_age),
    ('Job & Career', 'jobfinderph.com', $from_age,$to_age),
    ('Job & Career', 'jooble.org', $from_age,$to_age),
    ('Job & Career', 'mycareersfuture.gov.sg', $from_age,$to_age)";
} elseif ($_POST['keyword_id'] == 'Travel & Maps') {
    $value = "(category, website,from_age,to_age) VALUES
    ('Travel & Maps', 'google.com/maps', $from_age,$to_age),
    ('Travel & Maps', 'waze.com', $from_age,$to_age),
    ('Travel & Maps', 'tripadvisor.com', $from_age,$to_age),
    ('Travel & Maps', 'booking.com', $from_age,$to_age),
    ('Travel & Maps', 'agoda.com', $from_age,$to_age),
    ('Travel & Maps', 'airbnb.com', $from_age,$to_age),
    ('Travel & Maps', 'traveloka.com', $from_age,$to_age),
    ('Travel & Maps', 'cebupacificair.com', $from_age,$to_age),
    ('Travel & Maps', 'philippineairlines.com', $from_age,$to_age),
    ('Travel & Maps', 'skyscanner.com', $from_age,$to_age),
    ('Travel & Maps', 'expedia.com', $from_age,$to_age),
    ('Travel & Maps', 'trivago.com', $from_age,$to_age),
    ('Travel & Maps', 'rome2rio.com', $from_age,$to_age),
    ('Travel & Maps', 'klook.com', $from_age,$to_age),
    ('Travel & Maps', 'maps.me', $from_age,$to_age)";
} elseif ($_POST['keyword_id'] == 'Adult Content') {
    $value = "(category, website,from_age,to_age) VALUES
    ('Adult Content', 'pornhub.com', $from_age,$to_age),
    ('Adult Content', 'xvideos.com', $from_age,$to_age),
    ('Adult Content', 'xnxx.com', $from_age,$to_age),
    ('Adult Content', 'redtube.com', $from_age,$to_age),
    ('Adult Content', 'youporn.com', $from_age,$to_age),
    ('Adult Content', 'brazzers.com', $from_age,$to_age),
    ('Adult Content', 'onlyfans.com', $from_age,$to_age),
    ('Adult Content', 'fansly.com', $from_age,$to_age),
    ('Adult Content', 'adultfriendfinder.com', $from_age,$to_age),
    ('Adult Content', 'cam4.com', $from_age,$to_age),
    ('Adult Content', 'livejasmin.com', $from_age,$to_age),
    ('Adult Content', 'chaturbate.com', $from_age,$to_age),
    ('Adult Content', 'fapello.com', $from_age,$to_age),
    ('Adult Content', 'rule34.xxx', $from_age,$to_age),
    ('Adult Content', 'tnaflix.com', $from_age,$to_age)";
} elseif ($_POST['keyword_id'] == 'Gambling') {
    $value = "(category, website,from_age,to_age) VALUES
    ('Gambling', 'bet365.com', $from_age,$to_age),
    ('Gambling', '1xbet.com', $from_age,$to_age),
    ('Gambling', 'pinnacle.com', $from_age,$to_age),
    ('Gambling', 'draftkings.com', $from_age,$to_age),
    ('Gambling', 'fanduel.com', $from_age,$to_age),
    ('Gambling', '888casino.com', $from_age,$to_age),
    ('Gambling', 'betfair.com', $from_age,$to_age),
    ('Gambling', 'leovegas.com', $from_age,$to_age),
    ('Gambling', 'stake.com', $from_age,$to_age),
    ('Gambling', 'betway.com', $from_age,$to_age),
    ('Gambling', '22bet.com', $from_age,$to_age),
    ('Gambling', 'ladbrokes.com', $from_age,$to_age),
    ('Gambling', 'williamhill.com', $from_age,$to_age),
    ('Gambling', 'ggbet.com', $from_age,$to_age),
    ('Gambling', 'dafabet.com', $from_age,$to_age)";
} elseif ($_POST['keyword_id'] == 'Gaming') {
    $value = "(category, website,from_age,to_age) VALUES
    ('Gaming', 'steampowered.com', $from_age,$to_age),
    ('Gaming', 'epicgames.com', $from_age,$to_age),
    ('Gaming', 'roblox.com', $from_age,$to_age),
    ('Gaming', 'minecraft.net', $from_age,$to_age),
    ('Gaming', 'playstation.com', $from_age,$to_age),
    ('Gaming', 'xbox.com', $from_age,$to_age),
    ('Gaming', 'nintendo.com', $from_age,$to_age),
    ('Gaming', 'riotgames.com', $from_age,$to_age),
    ('Gaming', 'blizzard.com', $from_age,$to_age),
    ('Gaming', 'ea.com', $from_age,$to_age),
    ('Gaming', 'ubisoft.com', $from_age,$to_age),
    ('Gaming', 'twitch.tv', $from_age,$to_age),
    ('Gaming', 'ign.com', $from_age,$to_age),
    ('Gaming', 'gamefaqs.gamespot.com', $from_age,$to_age),
    ('Gaming', 'gamespot.com', $from_age,$to_age)";
}


    if (isset($_POST['whitelist'])) {
      $delete = "DELETE FROM group_block WHERE category = '".$_POST['keyword_id']."' ";
      if ($conn->query($delete) === TRUE) {
              $insert = "INSERT INTO group_whitelist $value";
      }
    } elseif (isset($_POST['blacklist'])) {
      $delete = "DELETE FROM group_whitelist WHERE category = '".$_POST['keyword_id']."' ";
      if ($conn->query($delete) === TRUE) {
              $insert = "INSERT INTO group_block $value";
      }
    }

    if (isset($insert)) {
        if ($conn->query($insert) === TRUE) {


          // Update blocking rules in background - no timeouts!
          $updateResult = FastApiHelper::backgroundUpdateAllDevices($conn);
          
          if (!$updateResult['success']) {
              error_log("Content filter background update failed: " . $updateResult['error']);
          }



           echo "<script src='../../js/sweetalert2.all.min.js'></script>
                <body onload='save()'></body>
                <script> 
                function save(){
                  Swal.fire({
                    title: 'Record Saved!',
                    icon: 'success',
                    confirmButtonText: 'OK'
                  }).then((result) => {
                    if (result.isConfirmed) {
                      window.location.href = 'index.php';
                    }
                  });
                }
                </script>";
        } else {
            echo "<script>alert('Error: " . $conn->error . "');</script>";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>BlockIt </title>
    <link rel="icon" type="image/x-icon" href="../../img/logo1.png" />

    <!-- Custom fonts for this template-->
    <link href="../../vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i"
        rel="stylesheet">
    <!-- Custom styles for this template-->
    <script src="../../js/html2canvas.min.js"></script>
    <link href="../../css/sb-admin-2.min.css" rel="stylesheet">`n    `n    <!-- Custom Color Palette -->`n    <link href="../../css/custom-color-palette.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../js/sweetalert2.all.js"></script>
    <script src="../../js/sweetalert2.css"></script>
    <script src="../../js/sweetalert2.js"></script>
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

       <?php include'../sidebar.php';?>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

               <?php include'../nav.php';?>

                <!-- Begin Page Content -->
                <div class="container-fluid">

                    <!-- Page Heading -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Content Filter</h1>
                    </div>

                    <!-- Content Row -->
                    <div class="row">

                    <div class="col-12">
                    <div class="card shadow-sm mb-5">
    <div class="card-body">
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h5 class="fw-bold">Age-Based Settings</h5>
          <small class="text-muted">Customize content filters based on age group</small>
        </div>
        <div class="text-success">
        </div>
      </div>

      <!-- App Categories -->
      <div class="mb-4">
        <h6 class="fw-semibold mb-2">App Categories</h6>
            <?php
              $query = "select category FROM group_whitelist GROUP BY category";
              $result = $conn->query($query);
              while ($row = $result->fetch_assoc()) {
                echo '<span class="badge bg-success text-white me-2 p-2 ml-2"><i class="bi bi-controller"></i> '.$row['category'].' ✓</span>';
              }

              $query = "select category FROM group_block GROUP BY category";
              $result = $conn->query($query);
              while ($row = $result->fetch_assoc()) {
                echo '<span class="badge bg-secondary text-white me-2 p-2 ml-2"><i class="bi bi-controller"></i> '.$row['category'].' ✗</span>';
              }
            ?>
      </div>

      <div class="mb-4">
        <form method="post">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-semibold mb-0">Keyword Filters</h6>
            <div>
              <button type="submit" name="whitelist" class="btn btn-outline-success btn-sm me-2">Whitelist</button>
              <button type="submit" name="blacklist" class="btn btn-outline-secondary btn-sm">Blacklist</button>
            </div>
          </div>

          <select class="form-control mb-2" name="keyword_id" required>
            <option value="" >---SELECT---</option>
            <?php
              $query = "SELECT * FROM category";
              $result = $conn->query($query);
              while ($row = $result->fetch_assoc()) {
                echo '<option value="'.$row['name'].'">'.$row['name'].'</option>';
              }
            ?>
          </select>

          <div class="row">
            <div class="col-6 mb-2">
              <label for="from_age" class="form-label">From Age</label>
              <input type="number" class="form-control" id="from_age" name="from_age" min="0" placeholder="Enter minimum age" required>
            </div>

            <div class="col-6 mb-3">
              <label for="to_age" class="form-label">To Age</label>
              <input type="number" class="form-control" id="to_age" name="to_age" min="0" placeholder="Enter maximum age" required>
            </div>
        </div>

        </form>
      </div>

      <!-- Impact Preview -->
      <div class="bg-success bg-opacity-10 rounded p-4">
        <div class="row">
          <div class="col-md-6 mb-3">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <h6 class="text-success fw-bold">Currently Allowed</h6>
              <div style="max-height: 200px; overflow-y: auto;">
              <ul class="list-unstyled mb-0">
                <?php
                  $query = "SELECT * FROM group_whitelist";
                  $result = $conn->query($query);
                  while ($row = $result->fetch_assoc()) {
                    echo '<li><i class="bi bi-check-circle text-success me-1"></i>'.$row['category'].'-'.$row['website'].'</li>';
                  }
                ?>
              </ul>
            </div>
            </div>
          </div>
          <div class="col-md-6 mb-3">
            <div class="bg-white rounded shadow-sm p-3 h-100">
              <h6 class="text-danger fw-bold">Currently Blocked</h6>
              <div style="max-height: 200px; overflow-y: auto;">
              <ul class="list-unstyled mb-0">
                <?php
                  $query = "SELECT * FROM group_block";
                  $result = $conn->query($query);
                  while ($row = $result->fetch_assoc()) {
                    echo '<li><i class="bi bi-check-circle text-success me-1"></i>'.$row['category'].'-'.$row['website'].'</li>';
                  }
                ?>
              </ul>
            </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>


                    </div>
            </div>
            <!-- End of Main Content -->

        </div>
        <!-- End of Content Wrapper -->
            <?php include'../footer.php';?>

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal-->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <a class="btn btn-primary" href="../../logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap core JavaScript-->
    <script src="../../vendor/jquery/jquery.min.js"></script>
    <script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Core plugin JavaScript-->
    <script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>

    <!-- Custom scripts for all pages-->
    <script src="../../js/sb-admin-2.min.js"></script>

    <!-- Page level plugins -->
    <script src="../../vendor/chart.js/Chart.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>


<script>


    document.getElementById('confirmButton').addEventListener('click', function () {
      Swal.fire({
        title: 'Are you sure?',
        text: "This will update the status to 0!",
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: 'Yes',
        cancelButtonText: 'No',
        reverseButtons: true
      }).then((result) => {
        if (result.isConfirmed) {
          $.ajax({
            url: 'update_status.php',
            type: 'POST',
            data: { status: 0 }, 
            success: function (response) {
              Swal.fire(
                'Updated!',
                'Wait for the data!',
                'success'
              );
            },
            error: function () {
              Swal.fire(
                'Error!',
                'There was an error updating the status.',
                'error'
              );
            }
          });
        } else if (result.dismiss === Swal.DismissReason.cancel) {
          Swal.fire(
            'Cancelled',
            'No changes were made.',
            'error'
          );
        }
      });
    });
</script>

</body>

</html>
<?php
}
else
{
    header('location:../../index.php');
}?>