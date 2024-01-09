<?php
session_start();
$con = mysqli_connect("localhost", "root", "", "table");

if (mysqli_connect_errno() ) {
// Jos yhteydessä on virhe, antaa errorin.
exit('Yhdistäminen ei onnistunut: ' . mysqli_connect_error());
}

// Funktiolle syötettävät parametri
function fetch_customer_data($con, $pdf_id_array) {
    // Muodostetaan SQL-kysely
    $query = "SELECT * FROM billings WHERE id IN (" . implode(",", $pdf_id_array) . ")";
  
    // // Suoritetaan kysely
    $query_run = mysqli_query($con, $query);
    
    // Alustetaan HTML-taulukko
    $output = '
        <!DOCTYPE html>
        <html>
        <head>
           <style>
        ' . file_get_contents('table.css') . '
    </style>
     </head>
    <body>
     <section id="pdf2">
     <div class="container">
     <div class="row">
        <div class="col-md-12">
          <div class="table-responsive">
           <table class="table">
            
  ';
 
  while ($row = mysqli_fetch_assoc($query_run)) {
    $name = $row["name"];
    $lastname = $row["lastname"];
    $address = $row["address"];
    $phone = $row["phone"];
    $email = $row["email"];
    $choice = $row["choice"];
    $today = date("d/m/Y");
    
    if ($choice == "horizontal") {
    $output .= '
        <thead>
            <tr>
              <th>Päiväys</th>
              <th>Nimi</th>
              <th>Osoite</th>
              <th>Puhelin</th>
              <th>Sähköposti</th>
            </tr>
          </thead>
          <tbody>
          ';
    $output .= '<tr>
                  <td> '.$today.' </td>
                  <td> '.$name.' '.$lastname.'</td>
                  <td> '.$address.' </td>
                  <td> '.$phone.' </td>
                  <td> '.$email.' </td>
                </tr>
    ';
   }
   
if ($choice == "upright") {
    $output .= '
        <tr>
            <th class="cell-header">Päiväys</th>
            <td class="cell-data">'.$today.'</td>
        </tr>
        <tr>
            <th class="cell-header">Nimi</th>
            <td class="cell-data">'.$name.' '.$lastname.'</td>
        </tr>
        <tr>
            <th class="cell-header">Osoite</th>
            <td class="cell-data">'.$address.'</td>
        </tr>
        <tr>
            <th class="cell-header">Puhelin</th>
            <td class="cell-data">'.$phone.'</td>
        </tr>
        <tr>
            <th class="cell-header">Sähköposti</th>
            <td class="cell-data">'.$email.'</td>
        </tr>
   ';
 }
}
  
  $output .= '
          </tbody>
        </table>
       </div>
      </div>
      </div>
    </div>
    </section>
    </body>
    </html>
  ';

  return $output;
}

// Tarkista, onko lomake lähetetty
if (isset($_POST['send_multiple_email'])) {
    // Lataa tarvittavat kirjastot
    include "dompdf.php";
    require 'phpmailer/autoload.php';
    require 'phpmailer/phpmailer/src/PHPMailer.php';
    require 'phpmailer/phpmailer/src/SMTP.php';
    require 'phpmailer/phpmailer/src/Exception.php';
    // Hakee POST-pyynnön avulla lähetettyjä sähköpostiosoitteita 
    $email_array = explode(",", $_POST['multiple_email_id']);

    // Käy läpi jokainen valittu sähköpostiosoite ja lähetä sille PDF-tiedosto
    foreach ($email_array as $email_id) {
        $query = "SELECT * FROM billings WHERE id = $email_id";
        $query_run = mysqli_query($con, $query);
        $row = mysqli_fetch_array($query_run);
        
        // Tarkista, että sähköposti löytyy tietokannasta
        if ($row) {
            $name = $row['name'];
            $lastname = $row['lastname'];
            $email = $row["email"];
            $notes = $row["notes"];

            // PDF:n nimi
            $filename = md5(rand()) . '.pdf';
            // Kutsuu funktiota ja lataa sisältöä HTML-tiedostosta
            // antaa sille parametreiksi muuttujat
            $html_code = fetch_customer_data($con, array($email_id));
            $pdf = new Pdf();
            // Asettaa paperin koon ja suunnan
            $pdf->setPaper('A4', 'portrait');
            $pdf->loadHtml($html_code);
            // Muokkaa HTML:n PDF-muotoon
            $pdf->render();
            $file = $pdf->output();

            // Lähetä sähköposti
            $from_name = '';
            $from_email = "";
            $password = ''; 
            $mail = new PHPMailer\PHPMailer\PHPMailer();
            try {
                $mail->IsSMTP(); //Asettaa lähettämään viestin SMTP:n kautta
                $mail->SMTPAuth = true; //Asettaa SMTP-todennuksen
                $mail->SMTPSecure = 'tls';
                $mail->Host = 'smtp.gmail.com'; //Asettaa SMTP-isännät
                $mail->Port = '587'; //SMTP-palvelimen portin
                $mail->Username = $from_email;
                $mail->Password = $password;
                $mail->SetFrom("$from_email", "$from_name");
                $mail->addAddress($email);
                $mail->IsHTML(true);

                //Liite ja sisältö
                $mail->addStringAttachment($file, $filename);
                $mail->Subject = iconv('UTF-8', 'ISO-8859-1//TRANSLIT','Yhteystiedot');
                $mail->Body = nl2br(iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Hei '.$name.' '.$lastname.', '."\n\n".''.$notes.''));  

                //Lähetä sähköposti
                $mail->send();
                $_SESSION['status'] = "Pdf lähetetty!";
                header("Location: table.php");

            } catch (Exception $e) {
                $_SESSION['status'] = "Pdf ei voitu lähettää. Virhe: {$mail->ErrorInfo}";
                header("Location: table.php");
            }
        }
    }
}
?>