<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
	$servername = "127.0.0.1";
	$username = "zyccc";
	$password = "666";
	$dbname = "myDB";
	$conn = mysqli_connect($servername, $username, $password, $dbname);

	if (mysqli_connect_errno($conn)) {
		die("Database Connection Error:" . mysqli_connect_error());
		//file_put_contents("error.log",  "Database Connecfddtion Error:" . mysqli_connect_error() . PHP_EOL, FILE_APPEND);
	}
	
	
	$_GET = [
			'action' => 'register',
			'src_user' => 'yhj',
			'user' => 'yhj',
			'email' => 'abc@abc.com',
			'password' => '123',
			'amount' => '10000',
	];
	

	$action = $_GET['action'];

	switch($action) {
		case 'register':
			register($conn);
			//file_put_contents("error.log", "register" . PHP_EOL, FILE_APPEND);
			break;
		case 'login':
			login($conn);
			//file_put_contents("error.log", "login" . PHP_EOL, FILE_APPEND);
			break;
		case 'query_amount':
			query_amount($conn);
			//file_put_contents("error.log", "query_amount" . PHP_EOL, FILE_APPEND);
			break;
		case 'query_history':
			query_history($conn);
			break;
		case 'transfer':
			transfer($conn);
			break;
		default:
			//file_put_contents("error.log", "default" . PHP_EOL, FILE_APPEND);
			break;
	}

	function register($conn) {
		$user = hash("crc32", $_GET['user']);
		$email = hash("crc32", $_GET['email']);
		$password = hash("sha256", $_GET['password']);
		/*
		$user_res = mysqli_query($conn,"SELECT * FROM Guests
			WHERE user='$user'");
		$email_res = mysqli_query($conn,"SELECT * FROM Guests
			WHERE email='$email'");
		$same_user_num = mysqli_num_rows($user_res);
		$same_email_num = mysqli_num_rows($email_res);
		*/
		$user_sql = "SELECT user FROM Guests WHERE (user=?)";
		if ($stmt1 = mysqli_prepare($conn, $user_sql)) {
			$stmt1->bind_param("s",$user);
			$stmt1->execute();
			$stmt1->bind_result($user_list);
			$have_same_user = mysqli_stmt_fetch($stmt1);
			$stmt1->close();
		}

		$email_sql = "SELECT email FROM Guests WHERE (email=?)";
		if ($stmt2 = mysqli_prepare($conn, $email_sql)) {
			$stmt2->bind_param("s",$email);
			$stmt2->execute();
			$stmt2->bind_result($email_list);
			$have_same_email = mysqli_stmt_fetch($stmt2);
			$stmt2->close();
		}

		
		if ($have_same_user == 0 and $have_same_email == 0) {
			$upd_sql = "INSERT INTO Guests (user, email, password, amount)
				VALUES (?, ?, ?, 0)";
			if ($stmt3 = mysqli_prepare($conn, $upd_sql)) {
				$stmt3->bind_param("sss", $user, $email, $password);
				$stmt3->execute();
				$stmt3->close();
			}
			/*
			if (mysqli_query($conn, $sql)) {
				echo "Insert Success";
			}
			else {
				echo "Database Error: " . $sql . "<br>" . mysqli_error($conn);
			}
			*/
		}
		$result = [
				'user' => $have_same_user == 0, 
				'email' => $have_same_email == 0
		];
		echo json_encode($result);	
	}

	function login($conn) {
		$user = $_GET['user'];
		$password = $_GET['password'];
		/*
		$result = mysqli_query($conn, "SELECT * FROM Guests
			WHERE user='$user' AND password='$password'");
		*/
		$user_sql = "SELECT user FROM Guests WHERE (user=?) AND (password=?)";
		if ($stmt = mysqli_prepare($conn, $user_sql)) {
			$stmt->bind_param("ss",$user,$password);
			$stmt->execute();
			$stmt->bind_result($user_list);
			$success_login = mysqli_stmt_fetch($stmt);
			$stmt->close();
		}
		if ($success_login) {
			$ret = ['success' => True];
			echo json_encode($ret); 
		} else {
			$ret = ['success' => False];
			echo json_encode($ret);
		}
		//file_put_contents("error.log", "success" . PHP_EOL, FILE_APPEND);
	}
	
	function query_amount($conn) {
		$user = $_GET['user'];
		$sql = "SELECT amount FROM Guests WHERE user='$user'";
		$res = mysqli_query($conn, $sql) or die(mysqli_error($conn));
		if (mysqli_num_rows($res) == 1) {
			$row = mysqli_fetch_assoc($res);
			$result = ['success' => True, 'amount' => $row["amount"]];
		} else {
			$result = ['success' => False, 'amount' => 0.0];
		}
		echo json_encode($result);
	}

	function query_history($conn) {
		$user = $_GET['user'];
		$sql = "SELECT * FROM Logs WHERE src_user='$user' OR dst_user='$user'";
		$res = mysqli_query($conn, $sql) or die(mysqli_error($conn));
		if (mysqli_num_rows($res) == 0) {
			$result = ['state' => '1', 'table' => '-1'];
		} elseif (mysqli_num_rows($res) > 0) {
			$table = [];
			while ($row = $res->fetch_assoc()) {
				$processed_row = ['time' => $row['time'], 'src_user' => $row['src_user'], 'dst_user' => $row['dst_user'], 'trans_money'=>$row['trans_money'], 'curr_money' => 0];
				if ($row['src_user'] == $user) {
					$processed_row['curr_money'] = $row['src_curr_money'];
				} else {
					$processed_row['curr_money'] = $row['dst_curr_money'];
				}
				array_push($table, $processed_row);
			}
			$result = ['state' => '0', 'table' => $table];
		} else {
			$result = ['state' => '2', 'table' => '-1'];
		}
		echo json_encode($result);	
	}

	function transfer($conn) {
		$src_user = $_GET['src_user'];
		$dst_user = $_GET['dst_user'];
		$trans_amount = floatval($_GET['amount']);
		$sql = "SELECT user FROM Guests WHERE user='$dst_user'";
		$res = mysqli_query($conn, $sql);
		if (mysqli_num_rows($res) == 0 or $src_user == $dst_user) {
			echo '1'; return;	//目标不合法
		}
		else {
			$src = mysqli_query($conn, "SELECT amount FROM Guests WHERE user='$src_user'");
			$dst = mysqli_query($conn, "SELECT amount FROM Guests WHERE user='$dst_user'");
			if (mysqli_num_rows($src) == 0) {
				echo '3'; return;
			}
			$src = mysqli_fetch_assoc($src);
			$dst = mysqli_fetch_assoc($dst);
			if ($src['amount'] < $trans_amount) {
				echo '2'; return;
			}
			$src_curr = $src['amount'] - $trans_amount;
			$dst_curr = $dst['amount'] + $trans_amount;
			$time = date('Y-m-d H:i:s');
			$upd1 = "UPDATE Guests SET amount = '$src_curr'
				WHERE user='$src_user'";
			$upd2 = "UPDATE Guests SET amount = '$dst_curr'
				WHERE user='$dst_user'";
			$upd3 = "INSERT INTO Logs(time,src_user,dst_user,trans_money,src_curr_money,dst_curr_money)
				VALUES ('$time','$src_user','$dst_user','$trans_amount','$src_curr','$dst_curr')";
			$status1 = mysqli_query($conn, $upd1);
			$status2 = mysqli_query($conn, $upd2);
			$status3 = mysqli_query($conn, $upd3);
			if(!$status1 or !$status2 or !$status3) {
				echo '3'; 
				die('无法更新数据: ' . mysqli_error($conn));
			}
		}
		echo '0';
	}		
	mysqli_close($conn);
?>
